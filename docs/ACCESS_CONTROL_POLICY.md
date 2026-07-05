# WDC Access Control Policy

เอกสารนี้เป็นกติกากลางสำหรับระบบสิทธิ์ของ WDC Portal เวอร์ชันใช้งานภายในบริษัท

## หลักการ

- ใช้ Role เพื่อบอกหน้าที่ เช่น Employee, HR, IT, IAM Admin, Auditor
- ใช้ Permission เพื่อเปิดความสามารถรายเมนูหรือรายงาน เช่น `assets.manage`, `iam.users.manage`
- ใช้ Data Scope เพื่อจำกัดขอบเขตข้อมูล เช่น ของตัวเอง, แผนก, สาขา, ทั้งหมด
- สิทธิ์สูงสุดต้องแยกระหว่างผู้ดูแลระบบ, เจ้าของข้อมูล, และผู้ตรวจสอบ
- ห้ามลบข้อมูลธุรกิจถาวรโดยไม่จำเป็น ให้เปลี่ยนสถานะเป็นปิดงาน, archived, retired หรือ revoked แทน

## Role หลัก

| Role | ใช้สำหรับ | สิทธิ์หลัก |
| --- | --- | --- |
| Employee | พนักงานทั่วไป | ดูหน้าแรก, โปรไฟล์, รายชื่อพนักงาน, ประกาศ, แบบฟอร์ม |
| Supervisor | หัวหน้างาน | ดูข้อมูลทีมและงานที่เกี่ยวข้อง |
| HR | ฝ่าย HR | จัดการรายชื่อพนักงาน, ประกาศ HR, เรื่องร้องเรียน |
| IT | ฝ่าย IT | Helpdesk และงาน IT ที่รับผิดชอบ |
| IT Asset Officer | เจ้าหน้าที่ทรัพย์สิน IT | เพิ่ม/แก้ไข/ติดตามทรัพย์สินตาม scope |
| IT Asset Admin | ผู้ดูแลทรัพย์สิน IT | ตั้งค่า master data และเก็บประวัติ/จำหน่ายทรัพย์สิน |
| IAM Admin | ผู้ดูแลบัญชีและสิทธิ์ | สร้าง user, ระงับ user, จัด role ตาม approval |
| Auditor Read-only | ผู้ตรวจสอบ | อ่าน log และ export หลักฐานเท่านั้น |
| Admin | ผู้ดูแลระบบธุรกิจ | จัดการระบบตามสิทธิ์ที่ได้รับ |
| Super Admin | บัญชีฉุกเฉินสูงสุด | ใช้เฉพาะเหตุจำเป็นและต้อง review ภายหลัง |

## นโยบายข้อมูลสำคัญ

- HR เป็น data owner ของข้อมูลพนักงานและเรื่องร้องเรียน
- IT เป็น data owner ของ ticket, helpdesk และ IT asset
- Finance/Payroll ใช้ลิงก์ไปบริการเดิมจนกว่าจะมีระบบเชื่อมต่อที่อนุมัติแล้ว
- Auditor อ่านข้อมูลเพื่อการตรวจสอบเท่านั้น ห้ามแก้ไขข้อมูลธุรกิจ
- IAM Admin จัด user/role ตามคำขอที่อนุมัติ แต่ไม่ควรมีสิทธิ์แก้ข้อมูล HR หรือ IT โดยตรง

## IT Asset

- ปุ่มเก็บประวัติ/จำหน่ายทรัพย์สินใช้ permission `assets.delete` เดิมเพื่อไม่ทำให้ route และ policy เก่าพัง
- ระบบจะไม่ hard delete รายการทรัพย์สิน แต่จะเปลี่ยน `status` เป็น `retired`
- ทุกการเก็บประวัติจะบันทึก `asset_audit_logs` และ `activity_logs`
- การลบถาวรควรทำผ่านงานบำรุงรักษาที่มีเหตุผลด้าน retention หรือ PDPA เท่านั้น

## Workflow ก่อนเพิ่มสิทธิ์ใหม่

1. ระบุเจ้าของข้อมูลและผู้อนุมัติ
2. เพิ่ม permission key ให้ชัดว่าเป็น view, manage, approve, export หรือ admin
3. ผูก permission กับ role ที่เกี่ยวข้องเท่านั้น
4. เพิ่ม test สำหรับ role และ route ที่เกี่ยวข้อง
5. รัน `php artisan test` และ `npm.cmd run build`

## สิ่งที่ห้ามทำ

- ห้ามให้ role เดียวถือสิทธิ์ทุกระบบโดยไม่จำเป็น
- ห้ามใช้ Super Admin เป็นบัญชีทำงานประจำวัน
- ห้ามลบข้อมูล production ถาวรผ่าน UI ทั่วไป
- ห้ามข้าม audit log สำหรับงานสิทธิ์, พนักงาน, ticket, complaint และ asset

## Target Enterprise Roles

The target role model is described in [INTERNAL_PORTAL_BLUEPRINT.md](INTERNAL_PORTAL_BLUEPRINT.md). Use these as the standard role families when adding new permission presets:

- `Super Admin`: emergency full-system owner, not a daily-use account.
- `IT Admin`: IT ticket, access, asset, license, and user-operation authority.
- `HR Admin`: employee, onboarding, offboarding, HR announcement, and HR request authority.
- `Admin Officer`: building, meeting room, office equipment, and admin-service authority.
- `Manager`: team-level viewer and approver.
- `Approver`: scoped approval-only role for assigned workflows.
- `Employee`: self-service user for profile, directory, announcements, tickets, requests, and forms.
- `Auditor`: read-only reports, audit logs, and evidence export.

Every new module must define:

- menu permission key
- view permission key
- create/update/manage permission key if applicable
- approve permission key if applicable
- export permission key if applicable
- data scope rule: self, team, branch, department, or all company
- audit log events for sensitive actions
