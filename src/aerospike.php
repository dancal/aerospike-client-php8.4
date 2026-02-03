<?php
$br = (php_sapi_name() == "cli")? "":"<br>";

if(!extension_loaded('aerospike')) {
	if (function_exists('dl')) {
		dl('aerospike.' . PHP_SHLIB_SUFFIX);
	} else {
		echo "Aerospike extension is not loaded and dl() is not available.$br\n";
		echo "Try: php -d extension=aerospike." . PHP_SHLIB_SUFFIX . " " . basename(__FILE__) . "$br\n";
		echo "Or enable it in php.ini/conf.d: extension=aerospike." . PHP_SHLIB_SUFFIX . "$br\n";
		exit(2);
	}
}
$module = 'aerospike';
$functions = get_extension_funcs($module);
echo "Functions available in the test extension:$br\n";
foreach($functions as $func) {
    echo $func."$br\n";
}
echo "$br\n";
$function = 'confirm_' . $module . '_compiled';
if (extension_loaded($module)) {
	$str = $function($module);
} else {
	$str = "Module $module is not compiled into PHP";
}
echo "$str\n";
?>
