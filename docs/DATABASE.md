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
- `tickets`: IT Helpdesk tickets.
- `ticket_comments`: ticket conversation.
- `workflow_templates`: SmartFlow workflow types imported into WDC Portal, including menu, service team, SLA, and form schema metadata.
- `workflow_steps`: approval or resolution steps for each workflow type.
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

Railway should provide database credentials through environment variables instead of committed files.

Important identity rules:

- `users.employee_code` is the primary login for WDC Portal.
- `users.email` should hold the corporate email used by systems such as SmartFlow.
- `employees` can store directory fields from the Notion directory: English name, Thai name, nickname, BU, team, location, and extension number.
- SmartFlow imports should map old rows to `workflow_requests.external_source = smartflow`, `external_record_id`, `external_url`, and `external_payload`; do not store SmartFlow passwords.
- Do not store external-system passwords, national ID numbers, salary data, or payroll details in WDC Portal.
