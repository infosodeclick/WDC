<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    public const CATALOG = [
        ['key' => 'portal.dashboard.view', 'group' => 'Employee Portal', 'name' => 'ดู Dashboard', 'description' => 'เปิดหน้าแรกและข้อมูลสรุปของตนเอง', 'sort_order' => 10],
        ['key' => 'profile.view', 'group' => 'Employee Portal', 'name' => 'ดูโปรไฟล์พนักงาน', 'description' => 'เปิดข้อมูลส่วนตัวและเอกสารของตนเอง', 'sort_order' => 20],
        ['key' => 'directory.view', 'group' => 'Employee Portal', 'name' => 'ดูรายชื่อพนักงาน', 'description' => 'ค้นหาพนักงาน ทีม สาขา อีเมล และเบอร์ต่อ', 'sort_order' => 30],
        ['key' => 'directory.manage', 'group' => 'Employee Portal', 'name' => 'จัดการรายชื่อพนักงาน', 'description' => 'เพิ่ม แก้ไข ระงับ และซิงค์ข้อมูลรายชื่อพนักงานสำหรับ HR', 'sort_order' => 31],
        ['key' => 'meeting_rooms.view', 'group' => 'Employee Portal', 'name' => 'ดูห้องประชุม', 'description' => 'เปิดตารางจองห้องประชุมจาก Google Sheet และปุ่มจองห้องประชุม', 'sort_order' => 35],
        ['key' => 'announcements.view', 'group' => 'Content', 'name' => 'ดูประกาศ', 'description' => 'อ่านนโยบายและประกาศบริษัท', 'sort_order' => 40],
        ['key' => 'knowledge.view', 'group' => 'Content', 'name' => 'ดูเทรนนิ่ง', 'description' => 'อ่านบทความและดูวิดีโอเทรนนิ่ง', 'sort_order' => 50],
        ['key' => 'documents.view', 'group' => 'Content', 'name' => 'ดู/ดาวน์โหลดแบบฟอร์ม', 'description' => 'เปิดแบบฟอร์มบริษัทและเอกสารของตนเอง', 'sort_order' => 60],
        ['key' => 'documents.manage', 'group' => 'Content', 'name' => 'จัดการเอกสาร HR', 'description' => 'เข้าถึงเอกสารพนักงานตามขอบเขตข้อมูล', 'sort_order' => 70],
        ['key' => 'payroll.link', 'group' => 'Employee Portal', 'name' => 'เปิดลิงก์สลิปเงินเดือน', 'description' => 'ไปยังระบบ Payroll เดิมโดยไม่เก็บข้อมูลเงินเดือนใน WDC', 'sort_order' => 90],
        ['key' => 'tickets.create', 'group' => 'IT Helpdesk', 'name' => 'เปิดคำขอ IT', 'description' => 'แจ้งปัญหา IT ผ่าน SmartFlow Workflow และติดตามงานของตนเอง', 'sort_order' => 100],
        ['key' => 'tickets.manage', 'group' => 'IT Helpdesk', 'name' => 'ดูแล Helpdesk', 'description' => 'เห็นคิว IT Helpdesk และ legacy ticket ตามขอบเขตข้อมูล', 'sort_order' => 110],
        ['key' => 'it.portal.view', 'group' => 'IT Helpdesk', 'name' => 'เปิด IT Dashboard', 'description' => 'ดู Dashboard งาน IT Helpdesk', 'sort_order' => 120],
        ['key' => 'assets.view', 'group' => 'INVENTORY', 'name' => 'ดู INVENTORY', 'description' => 'เปิดหน้า Inventory และค้นหาทรัพย์สิน/อุปกรณ์ IT', 'sort_order' => 125],
        ['key' => 'assets.manage', 'group' => 'INVENTORY', 'name' => 'จัดการ INVENTORY', 'description' => 'เพิ่มรายการ เปลี่ยนสถานะ และสร้างเอกสารตรวจนับ', 'sort_order' => 126],
        ['key' => 'assets.reports', 'group' => 'INVENTORY', 'name' => 'ส่งออกรายงาน INVENTORY', 'description' => 'ดาวน์โหลด CSV และรายงานตรวจนับ Inventory', 'sort_order' => 127],
        ['key' => 'assets.settings.manage', 'group' => 'INVENTORY', 'name' => 'ตั้งค่าหมวดหมู่ INVENTORY', 'description' => 'เพิ่มและแก้ไขหมวดหมู่ สถานที่ และค่ากลางของระบบ Inventory', 'sort_order' => 128],
        ['key' => 'assets.delete', 'group' => 'INVENTORY', 'name' => 'เก็บประวัติ/จำหน่าย INVENTORY', 'description' => 'เปลี่ยนรายการเป็นสถานะจำหน่ายหรือเก็บประวัติแทนการลบจริง', 'sort_order' => 129],
        ['key' => 'workflows.create', 'group' => 'Workflow', 'name' => 'ส่งคำขออนุมัติ', 'description' => 'เปิดคำขอจาก workflow ที่ย้ายมาจาก SmartFlow', 'sort_order' => 130],
        ['key' => 'workflows.manage', 'group' => 'Workflow', 'name' => 'อนุมัติ/ติดตามคำขอ', 'description' => 'เห็นและอัปเดตคำขออนุมัติตามขอบเขตข้อมูล', 'sort_order' => 140],
        ['key' => 'complaints.create', 'group' => 'HR & Compliance', 'name' => 'ส่งเรื่องร้องเรียน', 'description' => 'ส่งเรื่องร้องเรียนแบบไม่ระบุผู้ส่งถึง HR', 'sort_order' => 150],
        ['key' => 'complaints.review', 'group' => 'HR & Compliance', 'name' => 'ตรวจเรื่องร้องเรียน', 'description' => 'เห็นและปรับสถานะเรื่องร้องเรียนตามขอบเขตข้อมูล', 'sort_order' => 160],
        ['key' => 'hr.portal.view', 'group' => 'HR Portal', 'name' => 'เปิด HR Portal', 'description' => 'เปิดหน้าหลังบ้าน HR', 'sort_order' => 170],
        ['key' => 'hr.onboarding.manage', 'group' => 'HR Portal', 'name' => 'เพิ่มพนักงานใหม่', 'description' => 'ส่งคำขอพนักงานใหม่ให้ IT เปิดระบบและอนุมัติแสดงรายชื่อ', 'sort_order' => 175],
        ['key' => 'hr.employees.manage', 'group' => 'HR Portal', 'name' => 'จัดการพนักงาน', 'description' => 'ดู เปิด/ปิดรายชื่อ และจัดสถานะพนักงานตามขอบเขตข้อมูล', 'sort_order' => 180],
        ['key' => 'hr.announcements.manage', 'group' => 'HR Portal', 'name' => 'จัดการประกาศ', 'description' => 'สร้างประกาศและแจ้งเตือนพนักงาน', 'sort_order' => 190],
        ['key' => 'it.onboarding.manage', 'group' => 'IT', 'name' => 'รับงานพนักงานใหม่', 'description' => 'รับรายการจาก HR เพื่อเปิดอีเมล user ระบบ และผูกทรัพย์สินให้พนักงานใหม่', 'sort_order' => 195],
        ['key' => 'admin.users.manage', 'group' => 'Admin', 'name' => 'จัดการผู้ใช้งาน', 'description' => 'สร้างผู้ใช้ กำหนด role สถานะ และ override สิทธิ์รายคน', 'sort_order' => 200],
        ['key' => 'admin.roles.manage', 'group' => 'Admin', 'name' => 'จัดการ role และสิทธิ์', 'description' => 'แก้ role template และ permission matrix', 'sort_order' => 210],
        ['key' => 'admin.activity.view', 'group' => 'Admin', 'name' => 'ดู Activity Log', 'description' => 'ตรวจสอบประวัติ Login และการทำงานสำคัญ', 'sort_order' => 220],
        ['key' => 'admin.system.manage', 'group' => 'Admin', 'name' => 'ตั้งค่าระบบหลังบ้าน', 'description' => 'สิทธิ์สูงสุดสำหรับแก้ระบบหลังบ้านและ workflow การดูแลระบบ', 'sort_order' => 230],
        ['key' => 'iam.users.manage', 'group' => 'IAM', 'name' => 'IAM: จัดการผู้ใช้งาน', 'description' => 'สร้าง/ระงับ user และจัดการ role ตาม approval โดยไม่ถือสิทธิ์ข้อมูลธุรกิจ', 'sort_order' => 240],
        ['key' => 'iam.roles.manage', 'group' => 'IAM', 'name' => 'IAM: จัดการ role และ scope', 'description' => 'กำหนด role template, scope และ override สิทธิ์รายคนตามคำขอที่อนุมัติ', 'sort_order' => 250],
        ['key' => 'audit.logs.view', 'group' => 'Audit', 'name' => 'Audit: ดู log', 'description' => 'ดู activity log และหลักฐานการใช้งานแบบ read-only', 'sort_order' => 260],
        ['key' => 'audit.logs.export', 'group' => 'Audit', 'name' => 'Audit: ส่งออกหลักฐาน', 'description' => 'เตรียมส่งออกหลักฐานสำหรับ auditor หรือ compliance review', 'sort_order' => 270],
        ['key' => 'system.breakglass.use', 'group' => 'Security', 'name' => 'ใช้บัญชีฉุกเฉิน Break-glass', 'description' => 'สิทธิ์ฉุกเฉิน ต้องมีเหตุผล ticket และ review หลังใช้งาน', 'sort_order' => 280],
    ];

    public const DATA_SCOPE_LABELS = [
        'own' => 'เฉพาะของตนเอง',
        'department' => 'เฉพาะแผนก',
        'all' => 'ทั้งบริษัท',
    ];

    protected $fillable = [
        'key',
        'group',
        'name',
        'description',
        'sort_order',
    ];

    public static function catalogKeys(): array
    {
        return collect(self::CATALOG)->pluck('key')->all();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('effect')
            ->withTimestamps();
    }
}
