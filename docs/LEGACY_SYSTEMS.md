# Legacy Systems Integration

WDC Portal should become the single starting point for employees, while legacy systems stay available during migration.

## Systems Observed

### WDC Information Directory

- Current system: public Notion directory.
- Purpose: employee telephone directory and internal contact lookup.
- Visible fields: Thai name, English name, nickname, business unit/team, position, location, phone extension, email groups.
- Migration direction: move directory fields into `employees` and manage updates from HR Portal later.

### SmartFlow

- Current system: `https://wdc.smartflow.pw`.
- Purpose: document workflows, approval tasks, IT Helpdesk, reporting, authorization, workflow settings, dynamic fields, export.
- Login method: corporate email and SmartFlow password.
- IT Helpdesk fields observed: title, cancel document request, VPN request, SAP B1 issue, AI-CRM issue, database/Remote Access request, details, attachments 1-4.
- IT Helpdesk workflow observed: manager approval for cancel document, IT accept/resolve case, AI-CRM accept/resolve case, SoftpowerIT accept/resolve case for SAP B1.
- Migration direction: WDC tickets now store `request_type` and `legacy_document_ref`; later phases can add file uploads and SmartFlow import/API sync if available.

### Payroll

- Current system: external payroll system.
- Login method: employee code and national ID number.
- Migration direction: keep payroll as an external link only. Do not store salary data or national ID numbers in WDC Portal.

## Identity Direction

- WDC Portal login should be the primary employee entry point.
- `users.employee_code` remains the main identity key for the new portal.
- `external_system_accounts` maps a WDC user to legacy login identifiers without storing legacy passwords.
- Store notes such as "uses corporate email" or "uses payroll ID check"; never store plaintext passwords or national ID numbers.

## Migration Phases

1. Portal hub: employees open WDC Portal first, then use linked legacy systems when needed.
2. Directory migration: HR imports employee directory fields into WDC Portal.
3. Helpdesk migration: new IT tickets mirror SmartFlow categories, then add file uploads and reporting.
4. Workflow migration: replace selected SmartFlow workflows only after the approval steps and data owners are confirmed.
