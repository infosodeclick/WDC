# Architecture

WDC is one Laravel application for Employee, HR, IT, Admin, Manager, Auditor, and executive users. The current restructure plan is maintained in [PORTAL_RESTRUCTURE.md](PORTAL_RESTRUCTURE.md), and the target internal-company portal blueprint is maintained in [INTERNAL_PORTAL_BLUEPRINT.md](INTERNAL_PORTAL_BLUEPRINT.md).

The product direction is one shared internal portal:

- Employee workspace: dashboard, profile, directory, announcements, training, helpdesk, complaints, forms, payroll link, and system links.
- HR workspace: employee management, announcements, forms, complaints review, and HR-facing administration.
- IT workspace: SmartFlow-style requests, helpdesk queue, IT dashboard, and IT asset management.
- Admin workspace: users, roles, permission overrides, menu access, activity logs, and system settings.
- Executive workspace: read-only operational overview for people, workflow, IT, complaints, and asset metrics.

The system should evolve toward the blueprint module by module. Do not rewrite the production stack only to match the future technology option; keep using the current Laravel, Blade, Bootstrap, MySQL, and Railway foundation until there is an approved migration reason.

The application uses shared identity tables and policy-style permission checks. Menu visibility is controlled by permissions, but controllers must enforce the same permission on every protected route.

## Request Flow

1. Employee logs in with `employee_code` and password.
2. Laravel session auth protects all portal routes.
3. Controllers load role and department context.
4. Blade views render role-aware navigation and action forms.
5. Activity logs record sensitive actions such as login, logout, ticket creation, document download, user management, and complaint status updates.
