#!/usr/bin/env bash
# exit on error
set -o errexit

composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan optimize:clear
php artisan optimize
