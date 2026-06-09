# Deployment

## Local

```powershell
composer install
npm.cmd install
php artisan migrate:fresh --seed
npm.cmd run build
php artisan serve
```

## Railway

Set environment variables in Railway:

- `APP_NAME`
- `APP_ENV=production`
- `APP_KEY`
- `APP_DEBUG=false`
- `APP_URL`
- `DB_CONNECTION=mysql`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `PAYROLL_URL`

The repository includes a `Dockerfile` and `railway.json`. The Docker image uses official PHP 8.4 with `pdo_mysql`/mysqlnd so it can connect to Railway MySQL 9 using `caching_sha2_password`.
