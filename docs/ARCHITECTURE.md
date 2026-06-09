# Architecture

WDC V1 is one Laravel application with three portals:

- Employee Portal: dashboard, profile, announcements, knowledge, helpdesk, complaints, documents, payroll link.
- HR Portal: employee status management, announcements, complaints overview.
- IT Helpdesk Portal: ticket queue and status workflow.

The application uses a shared `users` table, `roles` table, and `employees` profile table. Role checks are implemented in controllers for the V1 prototype.

## Request Flow

1. Employee logs in with `employee_code` and password.
2. Laravel session auth protects all portal routes.
3. Controllers load role and department context.
4. Blade views render role-aware navigation and action forms.
5. Activity logs record sensitive actions such as login, logout, ticket creation, document download, user management, and complaint status updates.
