#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f composer.json ] || [ ! -f composer.lock ]; then
    echo 'Mount the application source at /var/www/html before starting the development image.' >&2
    exit 1
fi

if [ ! -f vendor/autoload.php ] || [ ! -f vendor/composer/installed.php ] || [ composer.lock -nt vendor/composer/installed.php ]; then
    composer install --prefer-dist --no-interaction --no-progress
fi

php artisan config:clear --no-interaction > /dev/null
