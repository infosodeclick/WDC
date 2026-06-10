# WDC Access Control

WDC Portal ใช้สิทธิ์ 3 ชั้นเพื่อให้หลังบ้านตรงกับหน้าบ้าน

1. Role template
   - Employee, Supervisor, HR, Admin และ Super Admin
   - ใช้เป็นค่าเริ่มต้นของสิทธิ์และขอบเขตข้อมูล

2. User override
   - Super Admin สามารถ grant หรือ deny สิทธิ์รายคนได้
   - deny รายคนจะทับสิทธิ์ที่มาจาก role

3. Data scope
   - `own`: เห็นข้อมูลของตัวเอง
   - `department`: เห็นข้อมูลของแผนก
   - `all`: เห็นข้อมูลทั้งบริษัท

## Super Admin

บัญชี `EMP09999` ถูกโปรโมตเป็น `Super Admin` ผ่าน migration เพื่อไม่ให้ production ล็อกตัวเองออกจากหลังบ้านหลัง deploy

Super Admin มีสิทธิ์ครบทุก permission รวมถึง

- `admin.users.manage`
- `admin.roles.manage`
- `admin.activity.view`
- `admin.system.manage`

## Permission Groups

- Employee Portal: dashboard, profile, directory, systems, payroll
- Content: announcements, knowledge, documents
- IT Helpdesk: create/manage tickets and IT dashboard
- Workflow: create/manage approval requests migrated from SmartFlow
- HR & Compliance: complaints and review
- HR Portal: HR backend operations
- Admin: user, role, log, and system backend controls

## Operating Rule

เมนูใน `resources/views/layouts/app.blade.php` และ backend checks ใน controllers ใช้ permission keys เดียวกัน เช่น ถ้าปิด `tickets.manage` ผู้ใช้จะไม่เห็นเมนูศูนย์ IT และ backend จะไม่อนุญาตให้อัปเดตสถานะ Ticket
