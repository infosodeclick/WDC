# WDC Codex Guide

## Project

WDC is a Laravel 12 internal company portal for about 200 employees. It combines Employee Portal, HR Portal, and IT Helpdesk Portal in one app with shared users, roles, and database tables.

## Stack

- Backend: Laravel 12, PHP 8.4+
- Frontend: Blade, Bootstrap CDN, Vite CSS/JS
- Database: MySQL-compatible database. Local development uses MariaDB.
- Deploy target: Railway

## Safety Rules

- Inspect existing routes, migrations, controllers, and views before changing behavior.
- Keep changes scoped to the requested module.
- Do not store payroll data in this app. Use `PAYROLL_URL` to redirect to the existing payroll system.
- Complaint anonymity must be preserved: anonymous complaints should not store `reporter_id`.
- Run `npm.cmd run build` and `php artisan test` after code changes.
- If a verification error appears, fix only the related error.

## Delivery Workflow

- After an approved WDC change is implemented and verified locally, commit and push it to `infosodeclick/WDC` on GitHub.
- Let Railway build/deploy the pushed source, or redeploy from source when needed.
- Use the Railway MySQL database for production data. Create and link the Railway database service when it is missing.
- Keep database connectivity portable: support normal Laravel `DB_*` vars, URL-style vars, and Railway MySQL `MYSQL*` vars.
- For a real domain, set `APP_URL` to the HTTPS domain, keep `APP_DEBUG=false`, and verify `/up` after deploy.
- Verify the live Railway URL after deployment with a real browser or HTTP workflow, including login when the change touches authenticated pages.
- Report the Git commit, Railway deployment status, production URL, and verification result.

## Demo Accounts

All seeded demo accounts use password `password123`.

- `EMP00125` Employee
- `EMP00200` Supervisor / IT department
- `EMP01000` HR
- `EMP09999` Admin
