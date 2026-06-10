# Routes

Primary web routes:

- `GET /login`, `POST /login`, `POST /logout`
- `GET /dashboard`
- `GET /profile`
- `GET /directory`
- `GET /announcements`
- `GET /knowledge`
- `GET /tickets`, `POST /tickets`
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
- `POST /workflows/templates/{template}/favorite`
- `POST /workflows/{workflowRequest}/comments`
- `PATCH /workflows/{workflowRequest}/status`
- `GET /hr`, `POST /hr/announcements`, `PATCH /hr/employees/{user}/status`
- `GET /it`
- `GET /admin`, `POST /admin/users`, `PATCH /admin/users/{user}`

V1 uses Blade forms and session authentication rather than a JSON API. Add `routes/api.php` only when a separate frontend or mobile app needs structured API access.

`POST /tickets` accepts SmartFlow-aligned fields:

- `request_type`: `general`, `cancel_document`, `vpn_access`, `sap_b1`, `ai_crm`, or `remote_access`
- `legacy_document_ref`: optional reference such as an old SmartFlow document number

`GET /workflows` now mirrors SmartFlow document views with `view=all`, `tasks`, `authorizations`, `statistics`, `favorites`, or `workflows`. `POST /workflows` creates a WDC document number (`WDC-SF-YYYYMMDD-00000`) and accepts optional `form_payload[...]` and `attachment_links` fields for SmartFlow-style document metadata.

Workflow migration/admin routes:

- `POST /workflows/import`: accepts `smartflow_csv` from the SmartFlow export/template and upserts WDC workflow requests.
- `GET /workflows/import-template`: downloads a UTF-8 CSV template for old SmartFlow rows.
- `POST /workflows/templates`: Super Admin creates a new workflow template.
- `PATCH /workflows/templates/{template}`: Super Admin updates workflow metadata, SLA, form fields, active state, and steps.
- `POST /workflows/{workflowRequest}/comments`: requester/assignee/manager adds a workflow comment and optional attachment links.
