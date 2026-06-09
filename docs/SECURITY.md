# Security Notes

- Passwords are stored as Laravel password hashes.
- Anonymous complaints store `reporter_id` as `null`.
- Payroll slips are not stored in WDC. The app redirects to `PAYROLL_URL`.
- Document downloads are permission-checked before response.
- Activity logs avoid storing passwords, tokens, or payroll data.
- Production file uploads should validate file type, file size, and private storage visibility before enabling real uploads.
- Use Railway secrets or environment variables for database credentials.
