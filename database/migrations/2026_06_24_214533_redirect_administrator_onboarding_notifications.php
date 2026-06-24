<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $administrator = DB::table('users')->where('employee_code', 'administrator')->first();

        if (! $administrator) {
            return;
        }

        DB::table('notifications')
            ->where('user_id', $administrator->id)
            ->where('type', 'onboarding')
            ->update(['url' => '/admin?section=notifications']);
    }

    public function down(): void
    {
        $administrator = DB::table('users')->where('employee_code', 'administrator')->first();

        if (! $administrator) {
            return;
        }

        DB::table('notifications')
            ->where('user_id', $administrator->id)
            ->where('type', 'onboarding')
            ->where('url', '/admin?section=notifications')
            ->update(['url' => '/it']);
    }
};
