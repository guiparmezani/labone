#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
