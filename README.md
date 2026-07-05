# WDC Internal Company Portal

Laravel 12 internal company portal for WDC employees, HR, IT, Admin, managers, auditors, and executives.

## Features

- Employee-code login with role and permission controls.
- Employee dashboard, profile, directory, announcements, training, requests, forms, and payroll link.
- Employee directory imported from legacy sources, including employees, group mail, and showroom records.
- HR workflows for employee management, onboarding, profile-change requests, complaints, and announcements.
- IT workflows for helpdesk, onboarding tasks, access checklist, and IT inventory.
- Admin portal for users, role templates, user permission overrides, data scopes, and activity logs.
- Audit-friendly roadmap for Service Desk, Access Request, Offboarding, Asset, License, Approval Center, Notifications, and Executive Reports.

## Local Setup

```powershell
composer install
npm.cmd install
php artisan migrate:fresh --seed
php artisan portal:import-notion-directory
php artisan portal:import-smartflow .\storage\app\smartflow-export.csv
npm.cmd run build
php artisan serve
```

## Docs

- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [Routes](docs/API.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Security](docs/SECURITY.md)
- [Roadmap](docs/ROADMAP.md)
- [Internal Portal Blueprint](docs/INTERNAL_PORTAL_BLUEPRINT.md)
- [Legacy Systems Integration](docs/LEGACY_SYSTEMS.md)
- [Access Control](docs/ACCESS_CONTROL.md)
