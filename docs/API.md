# Routes

Primary web routes:

- `GET /login`, `POST /login`, `POST /logout`
- `GET /dashboard`
- `GET /profile`
- `GET /directory`
- `GET /announcements`
- `GET /knowledge`
- `GET /tickets`, `POST /tickets` compatibility shortcuts for IT Helpdesk
- `POST /tickets/{ticket}/comments`
- `PATCH /tickets/{ticket}/status`
- `GET /complaints`, `POST /complaints`
- `PATCH /complaints/{complaint}/status`
- `GET /documents`
- `GET /documents/{document}/download`
- `GET /systems`
- `GET /payroll`
- `GET /search`
- `GET /workflows`, `GET /workflows/export`, `GET /workflows/import-template`, `POST /workflows`
- `POST /workflows/import`
- `POST /workflows/templates`, `PATCH /workflows/templates/{template}`
- `POST /workflows/templates/sync-smartflow`
- `POST /workflows/templates/{template}/favorite`
- `POST /workflows/{workflowRequest}/comments`
- `PATCH /workflows/{workflowRequest}/status`
- `GET /hr`, `POST /hr/announcements`, `PATCH /hr/employees/{user}/status`
- `GET /it`
- `GET /admin`, `POST /admin/users`, `PATCH /admin/users/{user}`

V1 uses Blade forms and session authentication rather than a JSON API. Add `routes/api.php` only when a separate frontend or mobile app needs structured API access.

`GET /tickets` redirects employees to the WDC SmartFlow IT Helpdesk workflow template instead of opening a separate Ticket queue.

`POST /tickets` is kept for old forms and accepts SmartFlow-aligned fields, then creates a `workflow_requests` row for the SmartFlow `IT Helpdesk` template:

- `request_type`: `general`, `cancel_document`, `vpn_access`, `sap_b1`, `ai_crm`, or `remote_access`
- `legacy_document_ref`: optional reference such as an old SmartFlow document number

`GET /workflows` now mirrors SmartFlow document views with `view=all`, `tasks`, `authorizations`, `statistics`, `favorites`, or `workflows`. `POST /workflows` creates a WDC document number (`WDC-SF-YYYYMMDD-00000`) and accepts optional `form_payload[...]` and `attachment_links` fields for SmartFlow-style document metadata.

Workflow migration/admin routes:

- `POST /workflows/import`: accepts `smartflow_csv` from the SmartFlow export/template and upserts WDC workflow requests.
- `GET /workflows/import-template`: downloads a UTF-8 CSV template for old SmartFlow rows.
- `POST /workflows/templates`: Super Admin creates a new workflow template.
- `PATCH /workflows/templates/{template}`: Super Admin updates workflow metadata, SLA, form fields, active state, and steps.
- `POST /workflows/templates/sync-smartflow`: Super Admin re-syncs the checked-in SmartFlow catalog snapshot into `workflow_templates` and `workflow_steps`.
- `POST /workflows/{workflowRequest}/comments`: requester/assignee/manager adds a workflow comment and optional attachment links.

Workflow template form schema:

- `form_schema.fields`: dynamic fields copied from SmartFlow with `key`, `label`, `type`, `required`, optional `options`, and optional `help`.
- `form_schema.routing`: branch rules copied from SmartFlow step conditions, for example SAP B1 and AI-CRM Helpdesk paths.
- `form_schema.statuses`: WDC status/action flow used to mirror SmartFlow-style submit, review, accept, resolve, approve, reject, and complete actions.
- `workflow_steps.metadata`: SmartFlow step id, approvers, branch conditions, user-selectable flag, and source note.
