<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            [
                'category' => 'HR/ใบลา',
                'title' => 'แบบฟอร์มใบลา',
                'file_name' => 'leave-form.pdf',
                'summary' => 'แบบฟอร์มลางานสำหรับพนักงาน',
            ],
            [
                'category' => 'บัญชี/เบิกเงินสดย่อย',
                'title' => 'แบบฟอร์มเบิกเงินสดย่อย',
                'file_name' => 'petty-cash-form.pdf',
                'summary' => 'แบบฟอร์มเบิกเงินสดย่อยสำหรับงานบัญชี',
            ],
        ] as $document) {
            DB::table('employee_documents')->updateOrInsert(
                [
                    'employee_id' => null,
                    'category' => $document['category'],
                    'title' => $document['title'],
                ],
                [
                    ...$document,
                    'created_by' => null,
                    'mime_type' => 'application/pdf',
                    'is_company_wide' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('employee_documents')
            ->whereNull('employee_id')
            ->whereIn('category', ['HR/ใบลา', 'บัญชี/เบิกเงินสดย่อย'])
            ->whereIn('file_name', ['leave-form.pdf', 'petty-cash-form.pdf'])
            ->delete();
    }
};
