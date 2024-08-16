export XDEBUG_CONFIG="idekey=VSCODE"
php -d xdebug.mode=debug -d xdebug.client_host=127.0.0.1 -d xdebug.client_port=9003 -d xdebug.start_with_request=yes -d xdebug.profiler_enable=on ./vendor/bin/phpunit  $1 $2 $3 $4 $5 $6 $7 $8 $9
