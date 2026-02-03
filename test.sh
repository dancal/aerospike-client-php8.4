#!/bin/bash

AEROSPIKE_HOST=127.0.0.1 AEROSPIKE_PORT=3000 AEROSPIKE_NS=test AEROSPIKE_SET=php_smoke \
php -d extension=/app/projects/aerospike-client-php8.3/src/modules/aerospike.so \
/app/projects/aerospike-client-php8.3/aerospike_smoke_test.php