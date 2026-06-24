<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employee_onboarding_requests')
            ->select(['id', 'english_name', 'english_nickname'])
            ->orderBy('id')
            ->get()
            ->each(function (object $onboarding): void {
                $displayName = $onboarding->english_nickname
                    ? "{$onboarding->english_name} ({$onboarding->english_nickname})"
                    : $onboarding->english_name;

                DB::table('notifications')
                    ->where('type', 'onboarding')
                    ->where('body', $displayName)
                    ->update(['url' => "/onboarding/{$onboarding->id}"]);
            });
    }

    public function down(): void
    {
        DB::table('notifications')
            ->where('type', 'onboarding')
            ->where('url', 'like', '/onboarding/%')
            ->update(['url' => '/admin?section=notifications']);
    }
};
