# Database

Core tables:

- `roles`: Employee, Supervisor, HR, Admin.
- `departments`: HR, IT, accounting, warehouse, sales.
- `users`: login account with `employee_code`, password hash, role, active status.
- `employees`: employee profile linked to user and department.
- `employee_documents`: employee-specific and company-wide documents.
- `announcements`: company, policy, holiday, activity, department, urgent notices.
- `announcement_files`: PDF/image attachment metadata.
- `knowledge_articles`: internal handbook articles.
- `knowledge_videos`: training videos.
- `tickets`: IT Helpdesk tickets.
- `ticket_comments`: ticket conversation.
- `complaints`: suggestions, complaints, fraud reports, supervisor issues.
- `notifications`: user-facing notifications.
- `activity_logs`: audit trail.

Local development database:

- Database: `wdc`
- User: `wdc_app`
- Password: `wdc_local_dev`
- Host: `127.0.0.1`

Railway should provide database credentials through environment variables instead of committed files.
