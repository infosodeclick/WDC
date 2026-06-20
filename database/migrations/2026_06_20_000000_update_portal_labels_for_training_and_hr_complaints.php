<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('key', 'announcements.view')->update([
                'name' => 'ดูประกาศ',
                'description' => 'อ่านนโยบายและประกาศบริษัท',
            ]);

            DB::table('permissions')->where('key', 'knowledge.view')->update([
                'name' => 'ดูเทรนนิ่ง',
                'description' => 'อ่านบทความและดูวิดีโอเทรนนิ่ง',
            ]);

            DB::table('permissions')->where('key', 'documents.view')->update([
                'name' => 'ดู/ดาวน์โหลดแบบฟอร์ม',
                'description' => 'เปิดแบบฟอร์มบริษัทและเอกสารของตนเอง',
            ]);

            DB::table('permissions')->where('key', 'complaints.create')->update([
                'name' => 'ส่งเรื่องร้องเรียน',
                'description' => 'ส่งเรื่องร้องเรียนแบบไม่ระบุผู้ส่งถึง HR',
            ]);
        }

        if (Schema::hasTable('announcements')) {
            DB::table('announcements')
                ->where('category', '<>', 'นโยบาย')
                ->update(['category' => 'ประกาศ']);
        }

        if (Schema::hasTable('complaints')) {
            DB::table('complaints')->update([
                'type' => 'ร้องเรียน',
                'submitted_to' => 'hr',
                'is_anonymous' => true,
                'reporter_id' => null,
            ]);
        }

        if (Schema::hasTable('legacy_systems')) {
            DB::table('legacy_systems')
                ->where('key', 'wdc')
                ->update([
                    'summary' => 'ระบบใหม่สำหรับ Dashboard, โปรไฟล์พนักงาน, ประกาศ, เทรนนิ่ง, Ticket, ร้องเรียน และแบบฟอร์ม',
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('key', 'announcements.view')->update([
                'name' => 'ดูประกาศ',
                'description' => 'อ่านข่าวสารและประกาศบริษัท',
            ]);

            DB::table('permissions')->where('key', 'knowledge.view')->update([
                'name' => 'ดูศูนย์ความรู้',
                'description' => 'อ่านคู่มือและดูวิดีโอความรู้',
            ]);

            DB::table('permissions')->where('key', 'documents.view')->update([
                'name' => 'ดู/ดาวน์โหลดเอกสาร',
                'description' => 'เปิดเอกสารบริษัทและเอกสารของตนเอง',
            ]);

            DB::table('permissions')->where('key', 'complaints.create')->update([
                'name' => 'ส่งเรื่องร้องเรียน/เสนอแนะ',
                'description' => 'ส่งเรื่องถึง HR หรือผู้บริหาร',
            ]);
        }
    }
};
