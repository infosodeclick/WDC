# WDC Internal Portal Blueprint

เอกสารนี้คือเป้าหมายระบบระยะยาวของ WDC Portal สำหรับ HR, IT, Admin, Manager, Employee, Auditor และผู้บริหาร ใช้เป็นแผนแม่บทก่อนแตกงานพัฒนาเป็น feature ย่อย

## 1. เป้าหมายระบบ

WDC Portal ต้องเป็นศูนย์กลางสำหรับงานภายในบริษัท:

- รับแจ้งเรื่อง, แจ้งปัญหา, แจ้งคำขอ, ข้อเสนอแนะ และ complaint
- ดูข้อมูลพนักงาน แยกตามสาขา แผนก ตำแหน่ง สถานะ และผู้ถือครองทรัพย์สิน
- แจ้งพนักงานเริ่มงานใหม่ เพื่อให้ HR, Manager, IT และ Admin ทำงานต่อเนื่องกัน
- จัดการทรัพย์สิน IT เช่น Notebook, PC, Monitor, Printer, License, Network, CCTV และ Server
- ติดตามสถานะงาน HR, IT, Admin และคำขอรออนุมัติ
- เก็บประวัติว่าใครทำอะไร เมื่อไร อนุมัติโดยใคร และเปลี่ยนแปลงข้อมูลอะไร
- ทำ Dashboard และ Report ให้ผู้บริหารดูภาพรวม Ticket, SLA, Asset, Access, Employee และ Audit ได้

## 2. โมดูลหลัก

### Dashboard

Dashboard ต้องสรุปภาพรวมทั้งหมด:

| รายการ | รายละเอียด |
| --- | --- |
| เรื่องแจ้งทั้งหมด | แยกตาม New, Assigned, In Progress, Waiting, Resolved, Closed, Rejected |
| พนักงานทั้งหมด | แยกตามสาขา แผนก สถานะ Active, Probation, Resigned |
| พนักงานเริ่มงานใหม่ | รายการที่ HR แจ้งแล้ว แต่ IT/Admin/Manager ยังดำเนินการไม่ครบ |
| ทรัพย์สิน IT | จำนวน Notebook, PC, Monitor, Printer, License และมูลค่ารวม |
| Warranty Alert | อุปกรณ์ใกล้หมดประกันล่วงหน้า 30, 60, 90 วัน |
| License Alert | License ใกล้หมดอายุ |
| งาน IT ค้าง | งานที่ยังไม่ปิดและงานที่เกิน SLA |
| รายการรออนุมัติ | เปิดสิทธิ์, ขออุปกรณ์, ขอ software, offboarding, asset transfer |

### Service Desk / Ticket / Complaint & Request

ควรใช้ชื่อกลาง เช่น `แจ้งเรื่อง`, `Ticket`, หรือ `Complaint & Request` แทนการใช้คำว่า `ร้องเรียน` อย่างเดียว

ประเภทเรื่อง:

- IT Support: คอมเสีย, Internet, Program, Email, Printer, VPN
- HR: ข้อมูลพนักงาน, เอกสาร, สวัสดิการ, ลางาน
- Admin: อาคาร, ห้องประชุม, อุปกรณ์สำนักงาน
- Safety: ความปลอดภัย, อุบัติเหตุ, จุดเสี่ยง
- Complaint: ข้อร้องเรียนทั่วไป
- Suggestion: ข้อเสนอแนะ
- Other: อื่น ๆ

ข้อมูล Ticket:

| Field | รายละเอียด |
| --- | --- |
| Ticket No. | เลขอัตโนมัติ เช่น `TKT-2026-00001` |
| ผู้แจ้ง | ดึงจากบัญชี Login |
| สาขา/แผนก | ดึงจากข้อมูลพนักงานและแก้ได้ตามสิทธิ์ |
| ประเภทเรื่อง | IT, HR, Admin, Safety, Complaint, Suggestion, Other |
| หัวข้อ/รายละเอียด | สรุปและอธิบายปัญหา |
| Priority | Low, Medium, High, Critical |
| Attachment | รูปภาพ, PDF, Excel และไฟล์ที่อนุญาต |
| Owner | IT, HR, Admin หรือผู้รับผิดชอบรายคน |
| Status | New, Assigned, In Progress, Waiting User, Waiting Approval, Resolved, Closed, Rejected |
| SLA | กำหนดเวลาตามหมวดและ priority |
| Closed At | วันที่ปิดงานอัตโนมัติ |

### Employee Directory

ใช้สำหรับดูพนักงานทั้งหมดและค้นหาตาม:

- บริษัท, สาขา, แผนก, ตำแหน่ง, Manager, สถานะพนักงาน
- All Employees, By Branch, By Department, New Employees, Resigned Employees, Org Chart, Asset Holder

ข้อมูลพนักงานหลัก:

| Field | รายละเอียด |
| --- | --- |
| Employee ID | รหัสพนักงาน |
| Thai/English Name | ชื่อ-นามสกุลไทยและอังกฤษ |
| Nickname | ชื่อเล่นไทย/อังกฤษ |
| Branch, Department, Position | โครงสร้างองค์กร |
| Manager | หัวหน้างาน |
| Email, Extension, Phone | จำกัดการมองเห็นตามสิทธิ์ |
| Start Date | วันที่เริ่มงาน |
| Status | Active, Probation, Resigned |
| Profile Image | รูปพนักงาน |
| Assigned Assets | Notebook, Mouse, Monitor ฯลฯ |
| Access Rights | SAP, CRM, Email, Shared Drive, VPN ฯลฯ |

### New Employee Onboarding

Workflow หลัก: HR -> Manager -> IT -> Admin -> Asset Handover -> Completed

ข้อมูลที่ HR กรอก:

- ชื่อ-นามสกุล, รหัสพนักงาน, วันที่เริ่มงาน
- สาขา, แผนก, ตำแหน่ง, Manager
- ประเภทพนักงาน: ประจำ, ทดลองงาน, สัญญาจ้าง
- อุปกรณ์ที่ต้องใช้: Notebook, PC, Monitor, Phone
- ระบบที่ต้องเปิด: Email, AD, SAP, CRM, POS, Shared Drive, VPN
- ระดับสิทธิ์ที่ต้องการและหมายเหตุ

สถานะ Onboarding:

| Status | ความหมาย |
| --- | --- |
| Draft | HR บันทึกร่าง |
| Submitted | HR ส่งเรื่องแล้ว |
| Waiting Manager Approval | รอหัวหน้าอนุมัติสิทธิ์ |
| Waiting IT | รอ IT ดำเนินการ |
| IT In Progress | IT กำลังทำ |
| Waiting Asset Handover | รอส่งมอบอุปกรณ์ |
| Completed | เสร็จสมบูรณ์ |
| Cancelled | ยกเลิก |

IT Checklist:

- Create AD Account
- Create Email
- Add Group Mail / Shared Drive
- Open SAP B1, CRM, POS, VPN ตามคำขอ
- Prepare Notebook / PC / Monitor / Phone
- Install Office, Antivirus, VPN, Printer
- Link Asset to employee
- Asset handover and user acknowledgement

### Access Request Management

ใช้กับพนักงานใหม่และพนักงานเดิมที่ต้องเพิ่ม/เปลี่ยน/ปิดสิทธิ์

ประเภทคำขอ:

- New Access
- Change Access
- Remove Access
- Temporary Access
- Resignation Disable
- Transfer Department

ระบบที่ต้องรองรับ:

- AD / Windows Login
- Email / Group Mail
- Shared Drive
- SAP B1
- CRM
- POS
- VPN
- Printer
- Google Drive / Zoho Drive
- Microsoft 365

Flow ตัวอย่าง: ผู้ขอ -> หัวหน้าแผนก -> เจ้าของระบบ -> IT -> Completed

### IT Asset Management

หมวดทรัพย์สิน:

- Computer: Notebook, Desktop, Mini PC
- Network: Router, Switch, Access Point
- Peripheral: Monitor, Keyboard, Mouse
- Printer: Printer, Scanner
- Mobile: Tablet, Mobile Phone
- License: Microsoft 365, Adobe, AutoCAD, Antivirus
- Server: Physical Server, VM, NAS
- CCTV: Camera, NVR
- Other

ข้อมูล Asset:

| Field | รายละเอียด |
| --- | --- |
| Asset Code | เช่น `NB-HO-2026-0001` |
| Asset Name | ชื่ออุปกรณ์ |
| Category, Brand, Model | ประเภทและรุ่น |
| Serial Number | หมายเลขเครื่อง |
| Spec | CPU, RAM, SSD ฯลฯ |
| Purchase Date, Warranty End Date | ใช้ทำ warranty alert |
| Vendor, Price | ข้อมูลจัดซื้อ |
| Branch, Location, Department | ที่ตั้ง |
| Current Holder | ผู้ถือครอง |
| Status | Stock, Assigned, In Use, Repair, Spare, Retired, Lost, Disposed |
| Files | ใบเสนอราคา, ใบกำกับ, รูปภาพ |
| QR Code | สำหรับติดอุปกรณ์และเปิดรายละเอียด |

ฟังก์ชันสำคัญ:

- เพิ่ม/แก้ไข/retire Asset
- ผูก Asset กับพนักงาน
- โอนย้าย Asset ระหว่างพนักงาน/สาขา
- ประวัติการถือครองย้อนหลัง
- Warranty และ License alert
- Handover document
- Export Excel/PDF
- QR Code และ scan QR
- Report ตามสาขา พนักงาน สถานะ มูลค่า และการซ่อม

### Employee Offboarding

Workflow: HR แจ้งลาออก -> Manager ยืนยัน -> IT ปิดสิทธิ์ -> รับคืนอุปกรณ์ -> Completed

Checklist:

- Disable AD Account
- Disable/Forward Email
- Remove Shared Drive
- Disable SAP, CRM, POS
- Revoke VPN
- รับคืน Notebook และอุปกรณ์อื่น
- Backup ข้อมูลถ้าจำเป็น
- Clear Device / Reinstall
- เปลี่ยนสถานะ Asset เป็น Stock, Repair หรือ Retired
- เปลี่ยนสถานะพนักงานเป็น Resigned

### IT Request

ใช้แยกจาก Ticket ปัญหา สำหรับคำขอ:

- Notebook, Monitor, Printer
- Software, License
- Email Group
- Internet / VPN
- Meeting equipment

Flow: ผู้ขอ -> หัวหน้าแผนก -> IT Manager -> Finance/Executive ถ้ามีค่าใช้จ่ายสูง -> ดำเนินการ

### Knowledge Base

หมวดตัวอย่าง:

- IT: เปลี่ยนรหัสผ่าน, ตั้งค่า email มือถือ, VPN, Printer
- HR: ลางาน, สลิปเงินเดือน, เอกสารพนักงาน
- Admin: จองห้องประชุม
- Security: ระวัง phishing

### Announcement

ฟังก์ชัน:

- สร้างประกาศ
- แนบรูป/PDF
- เลือกกลุ่มผู้เห็นประกาศ
- แยกตามสาขา
- ปักหมุด
- ตั้งวันหมดอายุ
- ดู read/unread
- แจ้งเตือนผ่าน email ในอนาคต

### Booking

Meeting Room Booking ควรรองรับ:

- จองห้องประชุม
- ดูปฏิทินห้อง
- จองอุปกรณ์ เช่น Projector, Speaker, Meeting Camera
- แจ้ง Admin เตรียมห้อง
- เชื่อม Google Calendar หรือ Outlook

### Approval Center

รวมรายการรออนุมัติ:

- Access Request
- Onboarding
- IT equipment/software request
- Repair request
- Asset transfer
- Offboarding

### Notification

ต้องมี notification ในเว็บและ email ในอนาคต:

- Ticket ใหม่
- งานใกล้เกิน SLA
- คำขอรออนุมัติ
- พนักงานใหม่ใกล้เริ่มงาน
- Warranty/license ใกล้หมดอายุ
- Offboarding
- งานปิดแล้ว

### Audit Trail

ต้องเก็บ log ทุก action สำคัญ:

- Login/Logout
- Create/Update/Delete
- Approval/Reject
- Access Grant/Revoke
- Asset Assign/Transfer/Retire
- Export
- File Upload/Download

## 3. Role และ Permission

Role หลัก:

| Role | สิทธิ์หลัก |
| --- | --- |
| Super Admin | จัดการระบบทั้งหมด ใช้เฉพาะงานจำเป็น |
| IT Admin | Ticket, Asset, Access, User ที่เกี่ยวกับ IT |
| HR Admin | Employee, Onboarding, Offboarding, HR announcement |
| Admin Officer | อาคาร ห้องประชุม อุปกรณ์สำนักงาน |
| Manager | อนุมัติคำขอของลูกทีม ดูข้อมูลทีม |
| Approver | อนุมัติเฉพาะเรื่องที่ได้รับมอบหมาย |
| Employee | แจ้งเรื่อง ดูโปรไฟล์ ดูประกาศ ดูข้อมูลที่อนุญาต |
| Auditor | อ่านรายงานและ log เท่านั้น |

Permission matrix เบื้องต้น:

| Function | Employee | Manager | HR | IT | Admin | Super Admin |
| --- | --- | --- | --- | --- | --- | --- |
| แจ้ง Ticket | Yes | Yes | Yes | Yes | Yes | Yes |
| ดู Ticket ตัวเอง | Yes | Yes | Yes | Yes | Yes | Yes |
| ดู Ticket ทั้งหมด | No | Team | Yes | Yes | Yes | Yes |
| ดูรายชื่อพนักงาน | Yes | Yes | Yes | Yes | Yes | Yes |
| แก้ไขข้อมูลพนักงาน | No | No | Yes | No | No | Yes |
| แจ้งพนักงานใหม่ | No | Yes | Yes | No | No | Yes |
| เปิดสิทธิ์ระบบ | No | Request | Request | Operate | No | Yes |
| อนุมัติสิทธิ์ | No | Yes | No | IT-owned | No | Yes |
| จัดการ Asset | No | View | View | Yes | No | Yes |
| Export Report | No | Team | Yes | Yes | Yes | Yes |
| ดู Audit Log | No | No | No | Yes | No | Yes |

## 4. เมนูระบบเป้าหมาย

- Dashboard
- Employee
  - Employee Directory
  - Organization Chart
  - New Employee Onboarding
  - Employee Transfer
  - Employee Offboarding
- Service Desk
  - My Tickets
  - Create Ticket
  - IT Support
  - HR Request
  - Admin Request
  - Complaint / Suggestion
- IT Management
  - IT Asset
  - Asset Assignment
  - Asset Transfer
  - Repair Management
  - License Management
  - Warranty Alert
- Access Management
  - New Access Request
  - Change Access Request
  - Remove Access Request
  - System Permission Matrix
  - Approval History
- Approval Center
  - Pending Approval
  - Approved
  - Rejected
- Announcement
  - Company News
  - IT Announcement
  - HR Announcement
- Knowledge Base
  - IT Guide
  - HR Guide
  - FAQ
- Booking
  - Meeting Room
  - Equipment Booking
- Reports
  - Ticket Report
  - Asset Report
  - Employee Report
  - Access Report
  - SLA Report
  - Audit Log
- System Setting
  - User Management
  - Role & Permission
  - Branch
  - Department
  - Position
  - Master Data
  - Workflow Setting

## 5. Workflow หลัก

### Ticket

พนักงานแจ้ง Ticket -> ระบบสร้าง Ticket No. -> ทีมรับงาน -> ดำเนินการ -> ผู้แจ้งยืนยัน -> ปิด Ticket -> เก็บ SLA และ history

### Onboarding

HR กรอกพนักงานใหม่ -> Manager อนุมัติสิทธิ์ -> IT เตรียม account/email/access/asset -> Admin เตรียมพื้นที่/อุปกรณ์สำนักงาน -> ส่งมอบอุปกรณ์ -> พนักงานเซ็นรับ -> Completed

### Access Request

ผู้ใช้หรือ Manager ส่งคำขอ -> หัวหน้าอนุมัติ -> เจ้าของระบบอนุมัติ -> IT เปิดสิทธิ์ -> แนบหลักฐาน -> Completed

### Offboarding

HR แจ้งลาออก -> Manager ยืนยันวันสุดท้าย -> IT ปิดสิทธิ์ -> รับคืนอุปกรณ์ -> Backup/Clear device -> เปลี่ยนสถานะพนักงาน -> Completed

### Asset Transfer

IT เลือก Asset -> เลือกผู้ถือครองเดิม -> เลือกผู้ถือครองใหม่ -> แนบหลักฐาน -> บันทึกประวัติ -> อัปเดต Current Holder

## 6. Reports

รายงาน IT:

- Monthly Ticket Report
- SLA Report
- IT Asset Report
- Asset by Branch
- Asset by Employee
- Warranty Expiry
- License Report
- Access Request Audit
- Offboarding Security Report

รายงานผู้บริหาร:

- Ticket volume by month/category/team
- Top 10 common issues
- SLA Performance
- Asset Summary and Value
- Warranty Forecast
- License Cost
- New Employee Readiness
- Security Access Report

## 7. โครงสร้างฐานข้อมูลเป้าหมาย

ตารางเป้าหมายระยะยาว:

- users, employees, branches, departments, positions, roles, permissions
- tickets, ticket_categories, ticket_comments, ticket_attachments, ticket_status_logs
- onboarding_requests, onboarding_tasks, onboarding_access_items, onboarding_asset_items
- offboarding_requests, offboarding_tasks
- access_requests, access_request_items, systems, system_roles, approval_logs
- assets, asset_categories, asset_assignments, asset_transfers, asset_repairs, asset_attachments, asset_warranty_logs
- licenses, license_assignments, license_renewals
- announcements, announcement_reads
- knowledge_base, knowledge_categories
- meeting_rooms, room_bookings, equipment_bookings
- notifications, audit_logs, settings

## 8. เทคโนโลยี

ระบบปัจจุบันใช้ Laravel, Blade, Bootstrap, MySQL และ Railway อยู่แล้ว จึงควรพัฒนาต่อบน stack เดิมเพื่อไม่ต้องรื้อระบบ production

ข้อเสนอ stack ใหม่ เช่น Next.js, React, Tailwind, Shadcn, PostgreSQL และ Prisma สามารถเก็บไว้เป็นแนวทางเวอร์ชันใหม่ในอนาคตได้ แต่ไม่ควร migrate ตอนนี้จนกว่าจะมีเหตุผลด้าน scale, team skill หรือ integration ที่ชัดเจน

แนวทาง hosting:

- ระยะปัจจุบัน: Railway/Cloud พร้อม MySQL managed database
- ระยะข้อมูลอ่อนไหวมากขึ้น: Private Cloud หรือ On-Premise
- ทุก environment ต้องใช้ `.env` และห้าม commit credential

## 9. Phase Development

### Phase 1: Core usable system

- Login, User, Role, Permission
- Employee Directory
- Branch, Department, Position master data
- Ticket System
- New Employee Onboarding
- Access Request
- IT Asset Management
- Basic Dashboard
- Audit Log
- Export Excel

### Phase 2: Operation and control

- Offboarding
- License Management
- Warranty Alert
- Approval Center
- Notification Email
- Announcement
- Knowledge Base
- Report Dashboard

### Phase 3: Integration and automation

- AD integration
- Google Workspace / Microsoft 365 integration
- SAP / CRM access metadata integration
- QR Code Asset
- Mobile responsive refinement
- E-Sign / Digital Acknowledgement
- Advanced Dashboard
- API Integration

## 10. หน้าจอสำคัญ

- Dashboard: Open Ticket, Overdue Ticket, New Employees, Pending IT Setup, Assets In Use, Warranty Expiring, Pending Approvals
- Employee Directory: Search, filter, profile, assigned assets, access rights
- Create Ticket: category, subject, detail, priority, attachment
- Onboarding Detail: checklist for AD, Email, License, Shared Drive, SAP, CRM, Notebook, Software, Handover
- Asset Detail: asset code, serial, spec, holder, warranty, status, history, files, QR

## 11. Security and Data Rules

- ข้อมูลพนักงานเป็นข้อมูลส่วนบุคคล ต้องจำกัดสิทธิ์การมองเห็น
- ไม่ให้ทุกคนเห็นเบอร์ส่วนตัว เงินเดือน เลขบัตรประชาชน หรือข้อมูลอ่อนไหว
- การเปิดสิทธิ์ระบบต้องมีหลักฐานอนุมัติ
- ใช้ soft delete หรือ status archive แทน hard delete
- ทุกการแก้ไขสำคัญต้องมี audit log
- จำกัดขนาดและประเภทไฟล์แนบ
- ต้องมี database backup
- Export สำหรับ audit ต้องบันทึกว่าใคร export เมื่อไร
- วางแผน password policy และ 2FA
- แยกสิทธิ์ HR, IT, Manager, Admin, Auditor ชัดเจน

## 12. UI Direction

แนวทาง UI:

- Modern enterprise dashboard
- Clean, dense, readable
- ใช้ง่ายบน desktop และมือถือ
- เหมาะกับงานประจำวัน ไม่ใช่ landing page
- ใช้สี WDC เป็นหลัก: yellow, black, gray พร้อมสีสถานะ

สีสถานะที่แนะนำ:

| Purpose | Color |
| --- | --- |
| Success | `#16A34A` |
| Warning | `#F59E0B` |
| Danger | `#DC2626` |
| Info | `#0284C7` |
| Background | `#F8FAFC` |
| Text | `#334155` |

## 13. สิ่งที่จำเป็นมาก

- Login / User Role
- Dashboard
- Employee Directory
- Ticket System
- New Employee Onboarding
- Access Request
- IT Asset Management
- Approval Flow
- Audit Log
- Report Export

## 14. สิ่งที่ควรมี

- Offboarding
- License Management
- Warranty Alert
- Announcement
- Knowledge Base
- Notification
- File Attachment
- QR Code Asset

## 15. สิ่งที่มีภายหลังได้

- Meeting Room Booking ขั้นสูง
- AD Integration
- Google / Microsoft Integration
- E-Sign
- Mobile App
- Advanced BI Dashboard

