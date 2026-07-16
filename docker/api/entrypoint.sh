#!/bin/sh
set -eu

if [ ! -f config/app_local.php ]; then
  cp config/app_local.example.php config/app_local.php
fi

composer install --no-interaction
mkdir -p tmp/cache tmp/sessions tmp/tests logs
chown -R www-data:www-data tmp logs
php bin/cake.php migrations migrate
chown -R www-data:www-data tmp logs

exec "$@"
