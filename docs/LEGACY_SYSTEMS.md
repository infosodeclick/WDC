# Legacy Systems Integration

WDC Portal should become the single starting point for employees, while legacy systems stay available during migration.

## Systems Observed

### WDC Information Directory

- Current system: public Notion directory.
- Purpose: employee telephone directory and internal contact lookup.
- Visible fields: Thai name, English name, nickname, business unit/team, position, location, phone extension, email groups.
- Structured import source: public Notion API for the collection view `All team members`.
- Latest observed import scope: 192 rows total: 150 employee records, 19 mail groups, 23 showroom/location records.
- Image handling: profile/showroom images are read from child image blocks and stored as proxied Notion image URLs in `employee_directory_entries.image_url`.
- Migration direction: move directory fields into `employees` and manage updates from HR Portal later.
- Current WDC implementation: `portal:import-notion-directory` imports/upserts Notion rows into `employee_directory_entries` and exposes them through `GET /directory`.

Run:

```powershell
php artisan portal:import-notion-directory
```

Use `--dry-run` to verify the source count without writing records.

### SmartFlow

- Current system: `https://wdc.smartflow.pw`.
- Purpose: document workflows, approval tasks, IT Helpdesk, reporting, authorization, workflow settings, dynamic fields, export.
- Login method: corporate email and SmartFlow password.
- IT Helpdesk fields observed: title, cancel document request, VPN request, SAP B1 issue, AI-CRM issue, database/Remote Access request, details, attachments 1-4.
- IT Helpdesk workflow observed:
  - Manager Approval: Any One, Senior_Management/user-selected manager, used for cancel document.
  - Accept Case: Any One, IT owners including พีรสิทธิ์ หนองรั้ง and ชนะพล จักรพันธ์.
  - Resolve Case: same IT owners, requires input for resolution.
  - AI-CRM Accept/Resolve Case: approver `thipaporn aisystem`, used when AI-CRM is selected.
  - SoftpowerIT Accept/Resolve Case: SAP B1 branch, assigned to the SoftpowerIT group.
- Migration direction: WDC tickets now store `request_type` and `legacy_document_ref`; later phases can add file uploads and SmartFlow import/API sync if available.
- Current WDC implementation: imported SmartFlow workflow names into `workflow_templates`, IT Helpdesk steps into `workflow_steps`, and added `GET /workflows` for new approval requests.

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
