#!/usr/bin/env sh
set -eu

: "${PORT:=8080}"

echo "Running database migrations..."
php artisan migrate --force

echo "Caching Laravel configuration..."
php artisan config:cache

echo "Caching Laravel routes..."
php artisan route:cache

echo "Caching Blade views..."
php artisan view:cache

echo "Starting Laravel server on 0.0.0.0:${PORT}..."
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
