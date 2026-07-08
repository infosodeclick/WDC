<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_steps')) {
            return;
        }

        DB::table('workflow_steps')
            ->whereIn('external_step_id', ['14', '16', '17', '2', '4', '5', '6', '37', '38', '39'])
            ->update([
                'mode' => 'all_required',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('workflow_steps')) {
            return;
        }

        DB::table('workflow_steps')
            ->whereIn('external_step_id', ['14', '16', '17', '2', '4', '5', '6', '37', '38', '39'])
            ->update([
                'mode' => 'any_one',
                'updated_at' => now(),
            ]);
    }
};
