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

## Demo Accounts

All seeded demo accounts use password `password123`.

- `EMP00125` Employee
- `EMP00200` Supervisor / IT department
- `EMP01000` HR
- `EMP09999` Admin
