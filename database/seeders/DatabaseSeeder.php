<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\AnnouncementFile;
use App\Models\Complaint;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\ExternalSystemAccount;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeVideo;
use App\Models\LegacySystem;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = collect([
            ['name' => 'Employee', 'slug' => 'employee', 'description' => 'พนักงานทั่วไป ใช้งานโปรไฟล์ ข่าวสาร คู่มือ Ticket และร้องเรียน'],
            ['name' => 'Supervisor', 'slug' => 'supervisor', 'description' => 'หัวหน้างาน เห็นทีมและช่วยติดตาม Ticket'],
            ['name' => 'HR', 'slug' => 'hr', 'description' => 'HR จัดการพนักงาน ประกาศ เอกสาร และเรื่องร้องเรียน'],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'ผู้ดูแลระบบ จัดการผู้ใช้ สิทธิ์ และ Log การใช้งาน'],
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'ผู้ดูแลสูงสุด แก้สิทธิ์และระบบหลังบ้านทั้งหมด'],
        ])->mapWithKeys(function (array $role) {
            return [$role['slug'] => Role::updateOrCreate(['slug' => $role['slug']], $role)];
        });

        $this->syncDefaultPermissions($roles);

        $departments = collect([
            ['code' => 'HR', 'name' => 'ทรัพยากรบุคคล', 'description' => 'ดูแลข้อมูลพนักงาน เอกสาร และประกาศ HR'],
            ['code' => 'IT', 'name' => 'เทคโนโลยีสารสนเทศ', 'description' => 'ดูแลระบบ Helpdesk และคู่มือระบบ'],
            ['code' => 'ACC', 'name' => 'บัญชี', 'description' => 'งานบัญชี การเงิน และเอกสารภายใน'],
            ['code' => 'WH', 'name' => 'คลังสินค้า', 'description' => 'การรับสินค้า คลัง และสต็อก'],
            ['code' => 'SALES', 'name' => 'ฝ่ายขาย', 'description' => 'ฝ่ายขายและงานเอกสารขาย'],
        ])->mapWithKeys(fn (array $department) => [$department['code'] => Department::create($department)]);

        $users = collect([
            [
                'employee_code' => 'EMP00125',
                'name' => 'สมชาย ใจดี',
                'email' => 'somchai@wdc.co.th',
                'role' => 'employee',
                'department' => 'SALES',
                'english_name' => 'Somchai Jaidee',
                'nickname' => 'ชาย',
                'position' => 'Sales Executive',
                'business_unit' => 'Bangkok Project (BU2)',
                'team' => 'Bangkok Project Team A',
                'location' => 'Nimitmai',
                'phone' => '081-234-5678',
                'extension_number' => '2101',
                'start_date' => '2023-04-01',
            ],
            [
                'employee_code' => 'EMP00200',
                'name' => 'มาลี พัฒนางาน',
                'email' => 'malee.it@wdc.co.th',
                'role' => 'supervisor',
                'department' => 'IT',
                'english_name' => 'Malee Pattanangan',
                'nickname' => 'ลี',
                'position' => 'IT Supervisor',
                'business_unit' => 'Information Technology',
                'team' => 'IT Support',
                'location' => 'Lumpini',
                'phone' => '082-111-2200',
                'extension_number' => '1801',
                'start_date' => '2021-08-15',
            ],
            [
                'employee_code' => 'EMP01000',
                'name' => 'กมลวรรณ HR',
                'email' => 'hr@wdc.co.th',
                'role' => 'hr',
                'department' => 'HR',
                'english_name' => 'Kamonwan HR',
                'nickname' => 'กมล',
                'position' => 'HR Manager',
                'business_unit' => 'HR',
                'team' => 'People Operations',
                'location' => 'Lumpini',
                'phone' => '083-222-1000',
                'extension_number' => '1803',
                'start_date' => '2020-01-05',
            ],
            [
                'employee_code' => 'EMP09999',
                'name' => 'ผู้ดูแลระบบ WDC',
                'email' => 'admin@wdc.co.th',
                'role' => 'super_admin',
                'department' => 'IT',
                'english_name' => 'WDC Administrator',
                'nickname' => 'แอดมิน',
                'position' => 'System Administrator',
                'business_unit' => 'Information Technology',
                'team' => 'System Admin',
                'location' => 'Lumpini',
                'phone' => '084-999-9999',
                'extension_number' => '1800',
                'start_date' => '2019-07-20',
            ],
        ])->mapWithKeys(function (array $profile) use ($roles, $departments) {
            $user = User::create([
                'role_id' => $roles[$profile['role']]->id,
                'employee_code' => $profile['employee_code'],
                'name' => $profile['name'],
                'email' => $profile['email'],
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]);

            Employee::create([
                'user_id' => $user->id,
                'department_id' => $departments[$profile['department']]->id,
                'english_name' => $profile['english_name'],
                'thai_name' => $profile['name'],
                'nickname' => $profile['nickname'],
                'position' => $profile['position'],
                'business_unit' => $profile['business_unit'],
                'team' => $profile['team'],
                'location' => $profile['location'],
                'phone' => $profile['phone'],
                'extension_number' => $profile['extension_number'],
                'start_date' => $profile['start_date'],
                'address' => 'อาคารสำนักงานใหญ่ WDC',
            ]);

            return [$profile['employee_code'] => $user];
        });

        if (isset($users['EMP00200'])) {
            $itPermissionIds = Permission::whereIn('key', ['tickets.manage', 'it.portal.view'])->pluck('id');
            $users['EMP00200']->permissionOverrides()->syncWithoutDetaching(
                $itPermissionIds->mapWithKeys(fn (int $permissionId) => [$permissionId => ['effect' => 'grant']])->all(),
            );
        }

        $announcements = collect([
            [
                'category' => 'ประกาศสำคัญ',
                'title' => 'ปรับปรุงระบบ ERP คืนวันศุกร์นี้',
                'body' => 'ระบบ ERP จะปิดปรับปรุงเวลา 22:00-23:30 น. กรุณาบันทึกงานก่อนเวลาดังกล่าว',
                'is_pinned' => true,
                'is_urgent' => true,
                'department_id' => $departments['IT']->id,
            ],
            [
                'category' => 'วันหยุด',
                'title' => 'ประกาศวันหยุดประจำไตรมาส',
                'body' => 'บริษัทประกาศวันหยุดเพิ่มเติมสำหรับกิจกรรมประจำปี สามารถดาวน์โหลดปฏิทินได้จากไฟล์แนบ',
                'is_pinned' => true,
                'is_urgent' => false,
                'department_id' => null,
            ],
            [
                'category' => 'นโยบาย',
                'title' => 'นโยบายการใช้งานอีเมลบริษัท',
                'body' => 'ห้ามส่งข้อมูลลูกค้าออกนอกระบบโดยไม่ได้รับอนุญาต และต้องเปิดใช้งาน MFA สำหรับบัญชีสำคัญ',
                'is_pinned' => false,
                'is_urgent' => false,
                'department_id' => null,
            ],
            [
                'category' => 'กิจกรรม',
                'title' => 'กิจกรรมอบรมความปลอดภัยในที่ทำงาน',
                'body' => 'พนักงานทุกคนลงทะเบียนเข้าร่วมอบรมผ่านฝ่าย HR ภายในสัปดาห์นี้',
                'is_pinned' => false,
                'is_urgent' => false,
                'department_id' => $departments['HR']->id,
            ],
            [
                'category' => 'บริษัท',
                'title' => 'เปิดตัว Employee Portal รุ่นทดลอง',
                'body' => 'บริษัทเริ่มทดลองใช้งาน WDC Portal สำหรับประกาศ คู่มือ แจ้งปัญหา และร้องเรียนในเว็บเดียว',
                'is_pinned' => true,
                'is_urgent' => false,
                'department_id' => null,
            ],
        ])->map(function (array $announcement) use ($users) {
            return Announcement::create([
                ...$announcement,
                'created_by' => $users['EMP01000']->id,
                'published_at' => now()->subDays(rand(0, 5)),
                'expires_at' => now()->addDays(30),
            ]);
        });

        $legacySystems = LegacySystem::all()->keyBy('key');
        $users->each(function (User $user) use ($legacySystems) {
            $accountRows = [
                'wdc-portal' => [
                    'login_identifier' => $user->employee_code,
                    'credential_note' => 'ใช้รหัสผ่าน WDC Portal',
                ],
                'employee-directory' => [
                    'login_identifier' => $user->email,
                    'credential_note' => 'เปิดอ่านจากลิงก์เดิม ระหว่างย้าย directory เข้าระบบใหม่',
                ],
                'smartflow-helpdesk' => [
                    'login_identifier' => $user->email,
                    'credential_note' => 'ใช้รหัสผ่าน SmartFlow เดิม และจะค่อย ๆ ย้ายประเภทคำขอเข้าระบบใหม่',
                ],
                'payroll' => [
                    'login_identifier' => $user->employee_code,
                    'credential_note' => 'ใช้เลขบัตรประชาชนในระบบเงินเดือนเดิม ไม่เก็บเลขบัตรใน WDC Portal',
                ],
            ];

            foreach ($accountRows as $systemKey => $account) {
                if (! isset($legacySystems[$systemKey])) {
                    continue;
                }

                ExternalSystemAccount::create([
                    'user_id' => $user->id,
                    'legacy_system_id' => $legacySystems[$systemKey]->id,
                    ...$account,
                    'last_verified_at' => now(),
                    'is_active' => true,
                ]);
            }
        });

        AnnouncementFile::create([
            'announcement_id' => $announcements[1]->id,
            'file_name' => 'holiday-calendar.pdf',
            'file_type' => 'pdf',
            'file_size_kb' => 248,
        ]);

        collect([
            ['category' => 'ERP', 'title' => 'วิธีเปิดใบสั่งขาย', 'summary' => 'ขั้นตอนการเปิดใบสั่งขายใน ERP สำหรับฝ่ายขาย', 'body' => 'เลือกเมนู Sales Order ตรวจสอบข้อมูลลูกค้า เพิ่มสินค้า ตรวจสอบเครดิต แล้วกดส่งอนุมัติ'],
            ['category' => 'คลังสินค้า', 'title' => 'วิธีรับสินค้าเข้าคลัง', 'summary' => 'คู่มือการรับสินค้าและตรวจนับสต็อก', 'body' => 'เปิดเอกสาร GRN ตรวจเลข PO สแกนสินค้า ตรวจจำนวน และบันทึกเข้าคลัง'],
            ['category' => 'IT', 'title' => 'วิธีรีเซ็ตรหัสผ่านอีเมล', 'summary' => 'แนวทางแจ้ง IT และตั้งรหัสผ่านใหม่อย่างปลอดภัย', 'body' => 'เปิด Ticket ระบุรหัสพนักงาน ห้ามส่งรหัสผ่านเดิม และตั้งรหัสผ่านใหม่อย่างน้อย 12 ตัวอักษร'],
        ])->each(fn (array $article) => KnowledgeArticle::create([
            ...$article,
            'author_id' => $users['EMP00200']->id,
            'is_published' => true,
            'published_at' => now()->subDays(3),
        ]));

        collect([
            ['category' => 'ERP', 'title' => 'สอนใช้งาน ERP เบื้องต้น', 'summary' => 'ภาพรวมเมนูหลักและ workflow', 'video_url' => 'https://example.com/videos/erp-intro', 'duration_minutes' => 18],
            ['category' => 'บัญชี', 'title' => 'สอนใช้งาน POS และรายงานยอดขาย', 'summary' => 'วิธีตรวจรายงาน POS หลังปิดรอบ', 'video_url' => 'https://example.com/videos/pos-report', 'duration_minutes' => 14],
            ['category' => 'IT', 'title' => 'วิธีเปิด Ticket ให้ทีม IT แก้ได้เร็ว', 'summary' => 'ข้อมูลที่ควรแนบเมื่อแจ้งปัญหา', 'video_url' => 'https://example.com/videos/helpdesk-ticket', 'duration_minutes' => 7],
        ])->each(fn (array $video) => KnowledgeVideo::create([
            ...$video,
            'author_id' => $users['EMP00200']->id,
            'is_published' => true,
            'published_at' => now()->subDays(2),
        ]));

        collect([
            ['employee_id' => $users['EMP00125']->employee->id, 'category' => 'สัญญาจ้าง', 'title' => 'สัญญาจ้างงาน', 'file_name' => 'employment-contract-EMP00125.pdf', 'is_company_wide' => false],
            ['employee_id' => $users['EMP00125']->employee->id, 'category' => 'หนังสือรับรอง', 'title' => 'หนังสือรับรองการทำงาน', 'file_name' => 'certificate-EMP00125.pdf', 'is_company_wide' => false],
            ['employee_id' => null, 'category' => 'แบบฟอร์ม HR', 'title' => 'แบบฟอร์มใบลา', 'file_name' => 'leave-form.pdf', 'is_company_wide' => true],
            ['employee_id' => null, 'category' => 'ระเบียบบริษัท', 'title' => 'คู่มือพนักงาน', 'file_name' => 'employee-handbook.pdf', 'is_company_wide' => true],
        ])->each(fn (array $document) => EmployeeDocument::create([
            ...$document,
            'created_by' => $users['EMP01000']->id,
            'summary' => 'เอกสารตัวอย่างสำหรับระบบ WDC Portal',
        ]));

        $ticket = Ticket::create([
            'reporter_id' => $users['EMP00125']->id,
            'assigned_to' => $users['EMP00200']->id,
            'department_id' => $departments['IT']->id,
            'title' => 'เข้า ERP ไม่ได้หลังเปลี่ยนรหัสผ่าน',
            'request_type' => 'sap_b1',
            'details' => 'ระบบแจ้งว่าบัญชีถูกล็อก กรุณาตรวจสอบสิทธิ์เข้าใช้งาน',
            'urgency' => 'high',
            'status' => 'in_progress',
            'legacy_document_ref' => 'SmartFlow IT Helpdesk demo',
        ]);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $users['EMP00200']->id,
            'body' => 'รับเรื่องแล้ว กำลังตรวจสอบ account lock ในระบบ ERP',
        ]);

        Complaint::create([
            'reporter_id' => null,
            'type' => 'เสนอแนะ',
            'status' => 'submitted',
            'subject' => 'อยากให้มีคู่มือ ERP เพิ่ม',
            'details' => 'ควรมีคลิปสั้นแยกตามเมนูที่ใช้งานบ่อย เพื่อให้พนักงานใหม่เรียนรู้เร็วขึ้น',
            'is_anonymous' => true,
            'submitted_to' => 'hr',
        ]);

        collect([
            ['type' => 'announcement', 'title' => 'ประกาศใหม่ 5 รายการ', 'body' => 'มีประกาศล่าสุดที่ควรอ่าน', 'url' => '/announcements'],
            ['type' => 'ticket', 'title' => 'Ticket ถูกตอบกลับ', 'body' => 'ทีม IT เพิ่มคำตอบใน Ticket ของคุณ', 'url' => '/tickets'],
            ['type' => 'document', 'title' => 'มีเอกสารใหม่', 'body' => 'HR เพิ่มแบบฟอร์มใบลาและคู่มือพนักงาน', 'url' => '/documents'],
        ])->each(fn (array $notification) => Notification::create([
            ...$notification,
            'user_id' => $users['EMP00125']->id,
        ]));

        ActivityLog::create([
            'user_id' => $users['EMP09999']->id,
            'action' => 'seed',
            'description' => 'Seeded WDC V1 demo data',
        ]);
    }

    private function syncDefaultPermissions($roles): void
    {
        if (! class_exists(Permission::class) || Permission::query()->count() === 0) {
            return;
        }

        $roleDefaults = [
            'employee' => [
                'scope' => 'own',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'systems.view',
                    'payroll.link',
                    'tickets.create',
                    'workflows.create',
                    'complaints.create',
                ],
            ],
            'supervisor' => [
                'scope' => 'department',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'systems.view',
                    'payroll.link',
                    'tickets.create',
                    'workflows.create',
                    'workflows.manage',
                    'complaints.create',
                ],
            ],
            'hr' => [
                'scope' => 'all',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'documents.manage',
                    'systems.view',
                    'payroll.link',
                    'tickets.create',
                    'workflows.create',
                    'workflows.manage',
                    'complaints.create',
                    'complaints.review',
                    'hr.portal.view',
                    'hr.employees.manage',
                    'hr.announcements.manage',
                ],
            ],
            'admin' => [
                'scope' => 'all',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'documents.manage',
                    'systems.view',
                    'payroll.link',
                    'tickets.create',
                    'tickets.manage',
                    'it.portal.view',
                    'workflows.create',
                    'workflows.manage',
                    'complaints.create',
                    'complaints.review',
                    'hr.portal.view',
                    'hr.employees.manage',
                    'hr.announcements.manage',
                    'admin.users.manage',
                    'admin.activity.view',
                ],
            ],
            'super_admin' => [
                'scope' => 'all',
                'permissions' => Permission::catalogKeys(),
            ],
        ];

        $permissionIds = Permission::query()->pluck('id', 'key');

        foreach ($roleDefaults as $roleSlug => $definition) {
            $role = $roles[$roleSlug] ?? null;

            if (! $role) {
                continue;
            }

            $role->update(['default_data_scope' => $definition['scope']]);
            $role->permissions()->sync(
                collect($definition['permissions'])
                    ->map(fn (string $permissionKey) => $permissionIds[$permissionKey] ?? null)
                    ->filter()
                    ->all(),
            );
        }
    }
}
