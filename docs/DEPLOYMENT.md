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

### Database service

Railway CLI auth on this machine may expire. If CLI is not logged in, create or confirm the database in the Railway dashboard:

1. Open the WDC Railway project.
2. Click **+ New** on the project canvas.
3. Select **Database > MySQL**.
4. Open the Laravel/WDC app service.
5. Add MySQL variables from the database service by reference, or copy the public/private connection values into the app service.
6. Redeploy the app service.

The app supports these database variable styles:

- Laravel style: `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- URL style: `DB_URL`, `DATABASE_URL`, or `MYSQL_URL`
- Railway MySQL style: `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`

The production start script runs `php artisan migrate --force`, so new tables and default admin/system data are created automatically after deploy.

Set environment variables in Railway:

- `APP_NAME`
- `APP_ENV=production`
- `APP_KEY`
- `APP_DEBUG=false`
- `APP_URL` such as `https://portal.wdc.co.th`
- `APP_TIMEZONE=Asia/Bangkok`
- `DB_CONNECTION=mysql`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `PAYROLL_URL`

The repository includes a `Dockerfile` and `railway.json`. The Docker image uses official PHP 8.4 with `pdo_mysql`/mysqlnd so it can connect to Railway MySQL 9 using `caching_sha2_password`.

## Custom Domain Or New Host

For a real company domain or another host later:

1. Point DNS to the target host according to that provider's instructions.
2. Set `APP_URL` to the final HTTPS domain.
3. If cookies need to work across subdomains, set `SESSION_DOMAIN=.wdc.co.th`; otherwise leave it empty/null for one domain.
4. Keep `APP_DEBUG=false`.
5. Set database credentials through environment variables only; never commit production `.env`.
6. Confirm `/up` returns success after deploy.
7. Log in with a Super Admin account and confirm `/admin`, `/dashboard`, `/directory`, `/complaints`, `/workflows`, and `/profile`.

The app is portable because it uses standard Laravel environment variables, Docker, MySQL-compatible storage, and does not depend on Railway-only code paths.

## Production Database Checklist

- Use Railway MySQL or another managed MySQL/MariaDB instance.
- Turn on backups/snapshots in the database provider before real employee use.
- Keep payroll, national ID numbers, and legacy passwords out of WDC Portal.
- Run migration automatically with `docker/start.sh` or manually with `php artisan migrate --force`.
- Create a database export before large imports from SmartFlow or Notion.
