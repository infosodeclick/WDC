# WDC Portal Restructure

This document is the working structure for turning WDC into one internal portal for Employee, HR, IT, and CEO users. The goal is to reduce duplicate logins, keep legacy systems reachable, and make the daily work usable on desktop and mobile.

## Product Direction

WDC should behave as one company operating system, not a collection of separate tools. The portal keeps one employee identity, one permission model, one activity log, and one navigation system. Legacy systems such as SmartFlow, payroll, and existing employee records remain connected while their core workflows are gradually moved into WDC.

## Role Workspaces

### Employee

- Dashboard with announcements, open requests, IT tickets, training, forms, and meeting rooms.
- Profile with employee data, documents, payroll link, and copy-ready contact details.
- Directory, announcements, training, complaints, forms, and system links.

### HR

- Employee management, departments, documents, forms, announcements, complaints, and permission requests.
- HR should be able to publish employee-facing content without IT editing code.
- Complaints are anonymous to normal users and always routed to HR.

### IT

- Helpdesk queue, SmartFlow-matched request catalog, comments, SLA, assignment, and status tracking.
- IT asset management for computers, devices, software, locations, owners, repair status, and audit history.
- Inventory and IT-only modules are controlled by menu permissions.

### CEO / Executive

- Read-only executive dashboard with people, helpdesk, complaints, workflow, and asset overview.
- Drill-down should respect data sensitivity and never expose complaint reporter identity.
- KPI cards should link to filtered operational lists instead of static reports.

## Information Architecture

Primary modules:

- Home: role-aware dashboard and urgent tasks.
- People: profile, directory, departments, and HR employee management.
- Requests: SmartFlow replacement workflows and approval tasks.
- IT: helpdesk, IT dashboard, assets, inventory, and knowledge for support.
- Training: articles, videos, policies, and onboarding.
- Forms: downloadable HR and company forms.
- Announcements: policy and announcement posts only.
- Complaints: anonymous complaint intake and HR review.
- Systems: legacy bridge to payroll, SmartFlow, employee directory sources, and other systems.
- Admin: users, roles, permissions, menu visibility, logs, and system settings.

## Mobile Rules

- Mobile users must reach the dashboard content immediately; the full sidebar must not appear above content.
- Use a bottom navigation for the top daily actions and a "More" menu for the complete role-aware menu.
- All tables must be wrapped or converted into list cards on small screens.
- Forms should use one column on mobile, clear labels, large tap targets, and useful keyboard types.
- Validate at 375px, 768px, 1024px, and desktop widths before deployment.

## Permission Model

Access should use three layers:

1. Role template: Employee, Supervisor, HR, IT, Admin, CEO.
2. User overrides: allow or deny a specific permission for a specific user.
3. Data scope: own data, department data, assigned queue, HR-only, IT-only, or executive read-only.

Every menu item should map to a permission key. Hiding a menu is not enough; the route and controller must enforce the same permission.

## Legacy Integration Plan

### SmartFlow

- Keep existing SmartFlow references, old document numbers, attachment URLs, workflow steps, and CSV import.
- New requests should be created in WDC first when the matching workflow already exists.
- SmartFlow should become a read-only fallback once users confirm WDC covers the workflow.

### Employee Directory / Notion Source

- Keep imported employee records and photos in WDC.
- Display employee data from WDC first, with source metadata for imported records.
- Use copy buttons and profile popups for mobile-friendly directory work.

### Payroll

- Payroll remains a secure external link from the employee profile.
- WDC should not store salary or national ID data unless a formal payroll migration is approved.

## Phased Build Plan

### Phase 1: Mobile Shell and Navigation

- Replace mobile sidebar with bottom navigation and a role-aware More menu.
- Verify all core pages fit on mobile and keep permission checks unchanged.

### Phase 2: Workspace Dashboards

- Add HR, IT, CEO, and Employee dashboard sections that show only relevant tasks.
- Link all KPI cards to filtered operational lists.

### Phase 3: Operational Depth

- Improve workflow request forms, approvals, IT assets, complaint review, and HR employee management.
- Add saved filters for IT, HR, and executive views.

### Phase 4: Legacy Retirement

- Compare SmartFlow, payroll, directory, and WDC usage.
- Decide which legacy flows remain linked and which move fully into WDC.
- Lock legacy systems to read-only where possible.

## Definition of Done

- `php artisan test` passes.
- `npm run build` passes.
- Role smoke tests pass for Employee, HR, IT, Admin, and CEO-like read-only access.
- Mobile smoke test passes at 375px and 768px.
- Production Railway deployment responds on the current WDC URL.
- No route relies only on hidden navigation for security.
