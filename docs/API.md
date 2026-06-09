# Routes

Primary web routes:

- `GET /login`, `POST /login`, `POST /logout`
- `GET /dashboard`
- `GET /profile`
- `GET /announcements`
- `GET /knowledge`
- `GET /tickets`, `POST /tickets`
- `POST /tickets/{ticket}/comments`
- `PATCH /tickets/{ticket}/status`
- `GET /complaints`, `POST /complaints`
- `PATCH /complaints/{complaint}/status`
- `GET /documents`
- `GET /documents/{document}/download`
- `GET /payroll`
- `GET /search`
- `GET /hr`, `POST /hr/announcements`, `PATCH /hr/employees/{user}/status`
- `GET /it`
- `GET /admin`, `POST /admin/users`, `PATCH /admin/users/{user}`

V1 uses Blade forms and session authentication rather than a JSON API. Add `routes/api.php` only when a separate frontend or mobile app needs structured API access.
