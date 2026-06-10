# WDC Employee Portal

Laravel 12 internal company portal for a 200-person organization.

## Features

- Employee-code login with 5 roles: Employee, Supervisor, HR, Admin, Super Admin.
- Employee dashboard with announcements, pending tickets, and new videos.
- Employee profile and document downloads.
- Legacy systems hub for Notion directory, SmartFlow IT Helpdesk, and Payroll links.
- Notion directory importer with employee photos, team/location filters, mail groups, and showroom records.
- SmartFlow Work Center with All Documents, Your Tasks, Authorization, Statistics, Export, Favorites, workflow templates, WDC document numbers, and SmartFlow-style form payloads.
- Announcements with pinned and urgent flags.
- Knowledge Base with articles and videos.
- IT Helpdesk ticket workflow.
- IT ticket request types aligned with the existing SmartFlow IT Helpdesk form.
- Complaint and suggestion workflow with anonymous option.
- HR Portal for announcements, employees, and complaints.
- Admin Portal for users, role templates, user permission overrides, data scopes, and activity logs.

## Local Setup

```powershell
composer install
npm.cmd install
php artisan migrate:fresh --seed
php artisan portal:import-notion-directory
npm.cmd run build
php artisan serve
```

Demo password for seeded users: `password123`.

## Demo Users

- `EMP00125` Employee
- `EMP00200` Supervisor / IT department
- `EMP01000` HR
- `EMP09999` Super Admin

## Docs

- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [Routes](docs/API.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Security](docs/SECURITY.md)
- [Roadmap](docs/ROADMAP.md)
- [Legacy Systems Integration](docs/LEGACY_SYSTEMS.md)
- [Access Control](docs/ACCESS_CONTROL.md)
