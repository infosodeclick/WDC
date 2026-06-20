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
        ['key' => 'directory.view', 'group' => 'Employee Portal', 'name' => 'ดูสมุดโทรศัพท์', 'description' => 'ค้นหาพนักงาน ทีม สาขา อีเมล และเบอร์ต่อ', 'sort_order' => 30],
        ['key' => 'announcements.view', 'group' => 'Content', 'name' => 'ดูประกาศ', 'description' => 'อ่านนโยบายและประกาศบริษัท', 'sort_order' => 40],
        ['key' => 'knowledge.view', 'group' => 'Content', 'name' => 'ดูเทรนนิ่ง', 'description' => 'อ่านบทความและดูวิดีโอเทรนนิ่ง', 'sort_order' => 50],
        ['key' => 'documents.view', 'group' => 'Content', 'name' => 'ดู/ดาวน์โหลดแบบฟอร์ม', 'description' => 'เปิดแบบฟอร์มบริษัทและเอกสารของตนเอง', 'sort_order' => 60],
        ['key' => 'documents.manage', 'group' => 'Content', 'name' => 'จัดการเอกสาร HR', 'description' => 'เข้าถึงเอกสารพนักงานตามขอบเขตข้อมูล', 'sort_order' => 70],
        ['key' => 'systems.view', 'group' => 'Employee Portal', 'name' => 'ดูศูนย์รวมระบบ', 'description' => 'เปิดลิงก์ระบบเดิมและหลักการ migration', 'sort_order' => 80],
        ['key' => 'payroll.link', 'group' => 'Employee Portal', 'name' => 'เปิดลิงก์สลิปเงินเดือน', 'description' => 'ไปยังระบบ Payroll เดิมโดยไม่เก็บข้อมูลเงินเดือนใน WDC', 'sort_order' => 90],
        ['key' => 'tickets.create', 'group' => 'IT Helpdesk', 'name' => 'เปิดคำขอ IT', 'description' => 'แจ้งปัญหา IT ผ่าน SmartFlow Workflow และติดตามงานของตนเอง', 'sort_order' => 100],
        ['key' => 'tickets.manage', 'group' => 'IT Helpdesk', 'name' => 'ดูแล Helpdesk', 'description' => 'เห็นคิว IT Helpdesk และ legacy ticket ตามขอบเขตข้อมูล', 'sort_order' => 110],
        ['key' => 'it.portal.view', 'group' => 'IT Helpdesk', 'name' => 'เปิด IT Dashboard', 'description' => 'ดู Dashboard งาน IT Helpdesk', 'sort_order' => 120],
        ['key' => 'workflows.create', 'group' => 'Workflow', 'name' => 'ส่งคำขออนุมัติ', 'description' => 'เปิดคำขอจาก workflow ที่ย้ายมาจาก SmartFlow', 'sort_order' => 130],
        ['key' => 'workflows.manage', 'group' => 'Workflow', 'name' => 'อนุมัติ/ติดตามคำขอ', 'description' => 'เห็นและอัปเดตคำขออนุมัติตามขอบเขตข้อมูล', 'sort_order' => 140],
        ['key' => 'complaints.create', 'group' => 'HR & Compliance', 'name' => 'ส่งเรื่องร้องเรียน', 'description' => 'ส่งเรื่องร้องเรียนแบบไม่ระบุผู้ส่งถึง HR', 'sort_order' => 150],
        ['key' => 'complaints.review', 'group' => 'HR & Compliance', 'name' => 'ตรวจเรื่องร้องเรียน', 'description' => 'เห็นและปรับสถานะเรื่องร้องเรียนตามขอบเขตข้อมูล', 'sort_order' => 160],
        ['key' => 'hr.portal.view', 'group' => 'HR Portal', 'name' => 'เปิด HR Portal', 'description' => 'เปิดหน้าหลังบ้าน HR', 'sort_order' => 170],
        ['key' => 'hr.employees.manage', 'group' => 'HR Portal', 'name' => 'จัดการพนักงาน', 'description' => 'ดูและระงับผู้ใช้ตามขอบเขตข้อมูล', 'sort_order' => 180],
        ['key' => 'hr.announcements.manage', 'group' => 'HR Portal', 'name' => 'จัดการประกาศ', 'description' => 'สร้างประกาศและแจ้งเตือนพนักงาน', 'sort_order' => 190],
        ['key' => 'admin.users.manage', 'group' => 'Admin', 'name' => 'จัดการผู้ใช้งาน', 'description' => 'สร้างผู้ใช้ กำหนด role สถานะ และ override สิทธิ์รายคน', 'sort_order' => 200],
        ['key' => 'admin.roles.manage', 'group' => 'Admin', 'name' => 'จัดการ role และสิทธิ์', 'description' => 'แก้ role template และ permission matrix', 'sort_order' => 210],
        ['key' => 'admin.activity.view', 'group' => 'Admin', 'name' => 'ดู Activity Log', 'description' => 'ตรวจสอบประวัติ Login และการทำงานสำคัญ', 'sort_order' => 220],
        ['key' => 'admin.system.manage', 'group' => 'Admin', 'name' => 'ตั้งค่าระบบหลังบ้าน', 'description' => 'สิทธิ์สูงสุดสำหรับแก้ระบบหลังบ้านและ workflow การดูแลระบบ', 'sort_order' => 230],
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
