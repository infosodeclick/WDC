# WDC Employee Portal

Laravel 12 internal company portal for a 200-person organization.

## Features

- Employee-code login with 4 roles: Employee, Supervisor, HR, Admin.
- Employee dashboard with announcements, pending tickets, and new videos.
- Employee profile and document downloads.
- Legacy systems hub for Notion directory, SmartFlow IT Helpdesk, and Payroll links.
- Imported employee directory from the old Notion directory with search and filters.
- SmartFlow-inspired request and approval center for common document workflows.
- Announcements with pinned and urgent flags.
- Knowledge Base with articles and videos.
- IT Helpdesk ticket workflow.
- IT ticket request types aligned with the existing SmartFlow IT Helpdesk form.
- Complaint and suggestion workflow with anonymous option.
- HR Portal for announcements, employees, and complaints.
- Admin Portal for users, roles, and activity logs.

## Local Setup

```powershell
composer install
npm.cmd install
php artisan migrate:fresh --seed
npm.cmd run build
php artisan serve
```

Demo password for seeded users: `password123`.

## Demo Users

- `EMP00125` Employee
- `EMP00200` Supervisor / IT department
- `EMP01000` HR
- `EMP09999` Admin

## Docs

- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [Routes](docs/API.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Security](docs/SECURITY.md)
- [Roadmap](docs/ROADMAP.md)
- [Legacy Systems Integration](docs/LEGACY_SYSTEMS.md)
