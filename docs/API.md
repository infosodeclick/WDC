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
- `GET /workflows`, `POST /workflows`
- `PATCH /workflows/{workflowRequest}/status`
- `GET /hr`, `POST /hr/announcements`, `PATCH /hr/employees/{user}/status`
- `GET /it`
- `GET /admin`, `POST /admin/users`, `PATCH /admin/users/{user}`

V1 uses Blade forms and session authentication rather than a JSON API. Add `routes/api.php` only when a separate frontend or mobile app needs structured API access.

`POST /tickets` accepts SmartFlow-aligned fields:

- `request_type`: `general`, `cancel_document`, `vpn_access`, `sap_b1`, `ai_crm`, or `remote_access`
- `legacy_document_ref`: optional reference such as an old SmartFlow document number
