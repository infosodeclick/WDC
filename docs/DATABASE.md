# Database

Core tables:

- `roles`: Employee, Supervisor, HR, Admin.
- `departments`: HR, IT, accounting, warehouse, sales.
- `users`: login account with `employee_code`, password hash, role, active status.
- `employees`: employee profile linked to user and department.
- `employee_directory_entries`: imported contact records from the old Notion directory, including employees, mail groups, and showroom contacts.
- `legacy_systems`: directory, SmartFlow, payroll, and portal links shown in the unified systems hub.
- `external_system_accounts`: maps a WDC user to legacy login identifiers without storing legacy passwords.
- `employee_documents`: employee-specific and company-wide documents.
- `announcements`: company, policy, holiday, activity, department, urgent notices.
- `announcement_files`: PDF/image attachment metadata.
- `knowledge_articles`: internal handbook articles.
- `knowledge_videos`: training videos.
- `tickets`: legacy IT Helpdesk tickets kept for compatibility/history; new IT requests are stored in `workflow_requests`.
- `ticket_comments`: legacy ticket conversation.
- `workflow_templates`: SmartFlow workflow types imported into WDC Portal, including menu, service team, SLA, dynamic field schema, routing rules, status/action flow, and source notes.
- `workflow_steps`: approval or resolution steps for each workflow type, including SmartFlow step id, action label, branch condition, approver metadata, and input-required flag.
- `workflow_requests`: employee-submitted requests based on workflow templates, with WDC document numbers, payload metadata, assignee/group, due dates, and optional SmartFlow import references.
- `workflow_request_attachments`: URL-based attachments imported from SmartFlow or added in WDC comments/requests.
- `workflow_template_favorites`: per-user favorite SmartFlow templates.
- `workflow_request_events`: audit trail for request creation and status changes.
- `complaints`: suggestions, complaints, fraud reports, supervisor issues.
- `notifications`: user-facing notifications.
- `activity_logs`: audit trail.

Local development database:

- Database: `wdc`
- User: `wdc_app`
- Password: `wdc_local_dev`
- Host: `127.0.0.1`

Railway or any future host should provide database credentials through environment variables instead of committed files.

Supported production variable formats:

- `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `DB_URL`, `DATABASE_URL`, or `MYSQL_URL`
- Railway MySQL aliases: `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`

The app start command runs `php artisan migrate --force`, so production schema changes and default system records are applied during deploy.

Important identity rules:

- `users.employee_code` is the primary login for WDC Portal.
- `users.email` should hold the corporate email used by systems such as SmartFlow.
- `employees` can store directory fields from the Notion directory: English name, Thai name, nickname, BU, team, location, and extension number.
- SmartFlow imports should map old rows to `workflow_requests.external_source = smartflow`, `external_record_id`, `external_url`, and `external_payload`; do not store SmartFlow passwords.
- Legacy WDC ticket rows should map to `workflow_requests.external_source = wdc_ticket` so IT Helpdesk has one active queue.
- SmartFlow workflow catalog sync is stored in `app\Services\SmartflowWorkflowCatalog`; migrations/upserts should keep the catalog backward-compatible by storing extra source detail in JSON fields rather than requiring legacy credentials at runtime.
- Do not store external-system passwords, national ID numbers, salary data, or payroll details in WDC Portal.

## Target Data Model

The long-term target table set is documented in [INTERNAL_PORTAL_BLUEPRINT.md](INTERNAL_PORTAL_BLUEPRINT.md). New database work should move toward that model gradually and keep existing data compatible.

Likely future table groups:

- Organization master data: `branches`, `positions`, expanded `departments`, manager relationships.
- Service desk: `ticket_categories`, `ticket_attachments`, `ticket_status_logs`, SLA settings.
- Onboarding/offboarding: `onboarding_requests`, `onboarding_tasks`, `onboarding_access_items`, `onboarding_asset_items`, `offboarding_requests`, `offboarding_tasks`.
- Access management: `access_requests`, `access_request_items`, `systems`, `system_roles`, `approval_logs`.
- IT assets: `asset_assignments`, `asset_transfers`, `asset_repairs`, `asset_attachments`, `asset_warranty_logs`.
- Licenses: `licenses`, `license_assignments`, `license_renewals`.
- Content and booking: `announcement_reads`, `knowledge_categories`, `room_bookings`, `equipment_bookings`.
- Operations: `settings`, richer `notifications`, richer `activity_logs` or dedicated audit log tables.

Database rules:

- Prefer additive migrations and backfills over destructive schema changes.
- Use status fields, archived flags, or soft deletes for business records.
- Keep audit history for approvals, access grants/revokes, asset movement, exports, and file actions.
- Keep file metadata in database, but store file contents in approved storage.
- Do not add salary, national ID, or external-system password storage without a separate security approval.
