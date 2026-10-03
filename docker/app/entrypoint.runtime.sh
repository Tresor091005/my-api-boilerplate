#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo 'Runtime image is missing Composer dependencies.' >&2
    exit 1
fi

php artisan config:cache --no-interaction
