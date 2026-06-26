<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_onboarding_requests')) {
            return;
        }

        Schema::table('employee_onboarding_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_onboarding_requests', 'cancel_requested_by')) {
                $table->foreignId('cancel_requested_by')
                    ->nullable()
                    ->after('hr_approved_by')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('employee_onboarding_requests', 'cancel_confirmed_by')) {
                $table->foreignId('cancel_confirmed_by')
                    ->nullable()
                    ->after('cancel_requested_by')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('employee_onboarding_requests', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('it_note');
            }

            if (! Schema::hasColumn('employee_onboarding_requests', 'cancel_requested_at')) {
                $table->timestamp('cancel_requested_at')->nullable()->after('hr_approved_at');
            }

            if (! Schema::hasColumn('employee_onboarding_requests', 'cancel_confirmed_at')) {
                $table->timestamp('cancel_confirmed_at')->nullable()->after('cancel_requested_at');
            }

            if (! Schema::hasColumn('employee_onboarding_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancel_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_onboarding_requests')) {
            return;
        }

        Schema::table('employee_onboarding_requests', function (Blueprint $table) {
            foreach (['cancel_requested_by', 'cancel_confirmed_by'] as $column) {
                if (Schema::hasColumn('employee_onboarding_requests', $column)) {
                    $table->dropForeign([$column]);
                    $table->dropColumn($column);
                }
            }

            foreach (['cancelled_at', 'cancel_confirmed_at', 'cancel_requested_at', 'cancel_reason'] as $column) {
                if (Schema::hasColumn('employee_onboarding_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
