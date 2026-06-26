<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_onboarding_requests')) {
            Schema::table('employee_onboarding_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_onboarding_requests', 'claimed_by_id')) {
                    $table->foreignId('claimed_by_id')
                        ->nullable()
                        ->after('requested_by')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('employee_onboarding_requests', 'claimed_at')) {
                    $table->timestamp('claimed_at')->nullable()->after('claimed_by_id');
                }
            });
        }

        if (Schema::hasTable('employee_onboarding_systems')) {
            Schema::table('employee_onboarding_systems', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_onboarding_systems', 'provisioned_by_id')) {
                    $table->foreignId('provisioned_by_id')
                        ->nullable()
                        ->after('it_asset_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnUpdate()
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('employee_onboarding_systems', 'provisioned_at')) {
                    $table->timestamp('provisioned_at')->nullable()->after('provisioned_by_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_onboarding_systems')) {
            Schema::table('employee_onboarding_systems', function (Blueprint $table) {
                if (Schema::hasColumn('employee_onboarding_systems', 'provisioned_by_id')) {
                    $table->dropForeign(['provisioned_by_id']);
                    $table->dropColumn('provisioned_by_id');
                }

                if (Schema::hasColumn('employee_onboarding_systems', 'provisioned_at')) {
                    $table->dropColumn('provisioned_at');
                }
            });
        }

        if (Schema::hasTable('employee_onboarding_requests')) {
            Schema::table('employee_onboarding_requests', function (Blueprint $table) {
                if (Schema::hasColumn('employee_onboarding_requests', 'claimed_by_id')) {
                    $table->dropForeign(['claimed_by_id']);
                    $table->dropColumn('claimed_by_id');
                }

                if (Schema::hasColumn('employee_onboarding_requests', 'claimed_at')) {
                    $table->dropColumn('claimed_at');
                }
            });
        }
    }
};
