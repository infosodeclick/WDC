<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_offboarding_requests')) {
            Schema::create('employee_offboarding_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('requested_by')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->foreignId('employee_user_id')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->foreignId('claimed_by_id')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->foreignId('it_completed_by')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->foreignId('hr_approved_by')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->string('employee_code');
                $table->string('employee_name');
                $table->string('thai_name')->nullable();
                $table->string('department')->nullable();
                $table->string('position')->nullable();
                $table->string('email')->nullable();
                $table->date('resignation_date')->nullable();
                $table->string('status')->default('pending_it');
                $table->text('hr_note')->nullable();
                $table->text('it_note')->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamp('it_completed_at')->nullable();
                $table->timestamp('hr_approved_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'resignation_date']);
            });
        }

        if (! Schema::hasTable('employee_offboarding_systems')) {
            Schema::create('employee_offboarding_systems', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_offboarding_request_id');
                $table->foreignId('it_asset_id')->nullable()->references('id')->on('it_assets')->cascadeOnUpdate()->nullOnDelete();
                $table->foreignId('completed_by_id')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->string('system_name');
                $table->string('username')->nullable();
                $table->string('email')->nullable();
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('employee_offboarding_request_id', 'eofs_request_fk')
                    ->references('id')
                    ->on('employee_offboarding_requests')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_offboarding_systems');
        Schema::dropIfExists('employee_offboarding_requests');
    }
};
