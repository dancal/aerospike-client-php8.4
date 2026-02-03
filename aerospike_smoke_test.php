<?php

declare(strict_types=1);

$errors = [];

function out(string $msg): void
{
    fwrite(STDOUT, $msg . "\n");
}

function err(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
}

function requireExtension(string $ext): void
{
    if (!extension_loaded($ext)) {
        err("[FAIL] PHP extension '$ext' is not loaded.");
        err("Hint: php -m | grep $ext");
        exit(2);
    }
    out("[OK] PHP extension '$ext' is loaded.");
}

function tryLoadAerospikeExtension(): void
{
    if (extension_loaded('aerospike')) {
        out("[OK] PHP extension 'aerospike' is loaded.");
        return;
    }

    $candidates = [];

    $candidates[] = __DIR__ . '/src/modules/aerospike.so';
    $candidates[] = __DIR__ . '/modules/aerospike.so';
    $candidates[] = __DIR__ . '/src/.libs/aerospike.so';

    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }

        out("[INFO] aerospike extension not loaded. Trying to load: {$path}");

        if (!function_exists('dl')) {
            err("[FAIL] dl() is not available in this PHP build.");
            err("Try running: php -d extension={$path} " . basename(__FILE__));
            exit(2);
        }

        // dl() generally expects a filename from extension_dir, but many CLI builds allow an absolute path.
        $ok = @dl($path);
        if ($ok && extension_loaded('aerospike')) {
            out("[OK] Loaded aerospike extension via dl().");
            return;
        }
    }

    err("[FAIL] PHP extension 'aerospike' is not loaded.");
    err("Tried candidate paths:");
    foreach ($candidates as $path) {
        err("  - {$path}");
    }
    err("Next steps:");
    err("  - From repo root: php -d extension=src/modules/aerospike.so aerospike_smoke_test.php");
    err("  - Or install it: (cd src && make install) and add extension=aerospike.so to your php.ini/conf.d");
    exit(2);
}

tryLoadAerospikeExtension();

out("PHP version: " . PHP_VERSION);

// Basic class/constant checks
if (!class_exists('Aerospike')) {
    err("[FAIL] Class Aerospike was not found. Extension may not be initialized correctly.");
    exit(3);
}
out("[OK] Class Aerospike exists.");

if (defined('Aerospike::OK')) {
    out("Aerospike::OK = " . (string)Aerospike::OK);
} else {
    err("[WARN] Aerospike::OK constant is not defined.");
}

// In-process extension info (avoid spawning a new php process which may not have the same extension flags)
$extVersion = phpversion('aerospike');
out('aerospike phpversion() = ' . ($extVersion === false ? '(unknown)' : $extVersion));
out('extension_dir = ' . (string)ini_get('extension_dir'));

$loaded = array_map('strtolower', get_loaded_extensions());
out("aerospike in get_loaded_extensions() = " . (in_array('aerospike', $loaded, true) ? 'yes' : 'no'));

// Optional live test (requires an Aerospike server)
$host = getenv('AEROSPIKE_HOST') ?: '';
$port = (int)(getenv('AEROSPIKE_PORT') ?: 3000);
$ns = getenv('AEROSPIKE_NS') ?: 'test';
$set = getenv('AEROSPIKE_SET') ?: 'php_smoke';

if ($host === '') {
    out("[SKIP] Live cluster test skipped.");
    out("       Set env AEROSPIKE_HOST (and optionally AEROSPIKE_PORT/AEROSPIKE_NS/AEROSPIKE_SET) to run it.");
    exit(0);
}

out("[INFO] Running live cluster test against {$host}:{$port} (ns={$ns}, set={$set})");

$config = [
    'hosts' => [
        ['addr' => $host, 'port' => $port],
    ],
];

try {
    $as = new Aerospike($config, false);
} catch (Throwable $e) {
    err("[FAIL] Aerospike constructor threw: " . $e->getMessage());
    exit(4);
}

if (!$as->isConnected()) {
    err("[FAIL] Not connected to Aerospike cluster.");
    exit(5);
}
out("[OK] Connected.");

$keyStr = 'smoke_' . getmypid() . '_' . bin2hex(random_bytes(4));
$key = $as->initKey($ns, $set, $keyStr);

$bins = [
    'hello' => 'world',
    'ts' => time(),
];

$status = $as->put($key, $bins);
if ($status !== Aerospike::OK) {
    err("[FAIL] put() failed. status=$status");
    $as->close();
    exit(6);
}
out("[OK] put() success.");

$record = null;
$status = $as->get($key, $record);
if ($status !== Aerospike::OK) {
    err("[FAIL] get() failed. status=$status");
    $as->close();
    exit(7);
}

$outBins = $record['bins'] ?? null;
if (!is_array($outBins) || ($outBins['hello'] ?? null) !== 'world') {
    err("[FAIL] get() returned unexpected record.");
    err(print_r($record, true));
    $as->close();
    exit(8);
}
out("[OK] get() returned expected bins.");

$status = $as->remove($key);
if ($status !== Aerospike::OK && $status !== Aerospike::ERR_RECORD_NOT_FOUND) {
    err("[WARN] remove() status=$status");
} else {
    out("[OK] remove() done.");
}

$as->close();
out("[OK] close() done.");

out("[PASS] aerospike.so smoke test completed successfully.");
