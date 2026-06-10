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
- SmartFlow workflow catalog now synced into WDC from the live structure read on 2026-06-10:
  - 12 workflows: E-MEMO, ใบเบิกสินค้า, ขอเครดิต/เปิดบัญชีใหม่, ใบคืนสินค้า, IT Helpdesk, ประสานงานภายใน, ขอสำรวจหน้างานและงานติดตั้ง, ขออนุมัติราคา/ขายสินค้า, ก่อสร้าง SHOWROOM, แจ้งสินค้าที่มีปัญหา, Developer/IT support, and ขออนุมัติคอนเทนต์ (Marketing).
  - Dynamic fields keep the original SmartFlow labels and field ids such as `dynamic_181`, `dynamic_62`, and `dynamic_197`.
  - Workflow steps keep the original SmartFlow step ids, order gaps, approver hints, user-selectable flags, input-required flags, and branch conditions.
  - Super Admin can refresh the local catalog from `/workflows` using the `Sync SmartFlow Catalog` action. This sync uses the checked-in catalog snapshot and does not store SmartFlow login credentials.
- Migration direction: employees should start work in WDC first. SmartFlow links remain only for old references or document types that have not been retired yet.
- Current WDC implementation: SmartFlow Work Center mirrors All Documents, Your Tasks, Authorization, Statistics, Export Excel, Favorites, and Workflows. New requests receive `WDC-SF-*` document numbers, keep SmartFlow-style form payloads, support template favorites, comments, URL attachments, assignee/SLA updates, and export CSV from WDC.
- IT Helpdesk consolidation: `/tickets` now points employees into the SmartFlow `IT Helpdesk` workflow template, `POST /tickets` creates `workflow_requests`, and legacy WDC ticket rows are migrated into workflow history with `external_source = wdc_ticket`.
- Migration import: Super Admin/manager can download the WDC SmartFlow CSV template from `/workflows/import-template`, export old rows from SmartFlow into matching columns, then upload the CSV at `/workflows`. The importer upserts requests by old document/reference, maps employee code/email to WDC users, creates missing workflow templates, stores old payload in `external_payload`, and keeps old document URLs in `external_url`.
- CLI import: `php artisan portal:import-smartflow path\to\smartflow-export.csv --default-requester=EMP09999`. Use `--dry-run` before large imports.

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
3. Helpdesk migration: new IT requests should start in WDC SmartFlow Work Center through the `IT Helpdesk` template; legacy `/tickets` routes remain compatibility shortcuts and old ticket rows are copied into workflow history.
4. Workflow migration: retire selected SmartFlow workflows after CSV history, approval steps, and data owners are confirmed in WDC.
