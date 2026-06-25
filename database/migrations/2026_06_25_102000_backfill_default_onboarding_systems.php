<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DEFAULT_SYSTEMS = [
        'WDC Portal',
        'Active Directory',
        'EMAIL',
        'ทรัพย์สิน',
    ];

    public function up(): void
    {
        DB::table('employee_onboarding_systems')
            ->whereIn('system_name', ['Email', 'email', 'E-mail', 'Mail'])
            ->update(['system_name' => 'EMAIL']);

        DB::table('employee_onboarding_requests')
            ->select(['id', 'employee_code'])
            ->orderBy('id')
            ->get()
            ->each(function (object $request): void {
                foreach (self::DEFAULT_SYSTEMS as $systemName) {
                    $exists = DB::table('employee_onboarding_systems')
                        ->where('employee_onboarding_request_id', $request->id)
                        ->where('system_name', $systemName)
                        ->exists();

                    if ($exists) {
                        if ($systemName === 'WDC Portal') {
                            DB::table('employee_onboarding_systems')
                                ->where('employee_onboarding_request_id', $request->id)
                                ->where('system_name', 'WDC Portal')
                                ->update(['username' => $request->employee_code]);
                        }

                        continue;
                    }

                    DB::table('employee_onboarding_systems')->insert([
                        'employee_onboarding_request_id' => $request->id,
                        'system_name' => $systemName,
                        'requested_access' => 'เปิดสิทธิ์เริ่มงานใหม่',
                        'username' => $systemName === 'WDC Portal' ? $request->employee_code : null,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('employee_onboarding_systems')
            ->whereIn('system_name', ['Active Directory', 'ทรัพย์สิน'])
            ->where('requested_access', 'เปิดสิทธิ์เริ่มงานใหม่')
            ->whereNull('username')
            ->whereNull('email')
            ->whereNull('it_asset_id')
            ->whereNull('notes')
            ->where('status', 'pending')
            ->delete();

        DB::table('employee_onboarding_systems')
            ->where('system_name', 'EMAIL')
            ->update(['system_name' => 'Email']);
    }
};
