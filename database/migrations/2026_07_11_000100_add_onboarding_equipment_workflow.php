<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_onboarding_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_onboarding_request_id');
            $table->foreign('employee_onboarding_request_id', 'onboarding_assets_request_fk')
                ->references('id')->on('employee_onboarding_requests')->cascadeOnDelete();
            $table->foreignId('it_asset_id');
            $table->foreign('it_asset_id', 'onboarding_assets_asset_fk')
                ->references('id')->on('it_assets')->restrictOnDelete();
            $table->string('status')->default('reserved');
            $table->foreignId('assigned_by_id')->nullable();
            $table->foreign('assigned_by_id', 'onboarding_assets_assignee_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_onboarding_request_id', 'it_asset_id'], 'onboarding_asset_unique');
            $table->index(['status', 'it_asset_id']);
        });

        DB::table('it_assets')
            ->where('status', 'active')
            ->whereNull('owner_id')
            ->where(function ($query): void {
                $query->whereNull('owner_name')->orWhere('owner_name', '');
            })
            ->update(['status' => 'stock']);

        DB::table('employee_onboarding_systems')
            ->whereNotNull('it_asset_id')
            ->orderBy('id')
            ->each(function ($system): void {
                $request = DB::table('employee_onboarding_requests')->find($system->employee_onboarding_request_id);

                if (! $request) {
                    return;
                }

                $delivered = in_array($request->status, ['it_completed', 'hr_approved'], true)
                    || $system->status === 'provisioned';

                DB::table('employee_onboarding_assets')->updateOrInsert(
                    [
                        'employee_onboarding_request_id' => $system->employee_onboarding_request_id,
                        'it_asset_id' => $system->it_asset_id,
                    ],
                    [
                        'status' => $delivered ? 'delivered' : 'reserved',
                        'assigned_by_id' => $system->provisioned_by_id,
                        'assigned_at' => $system->provisioned_at ?? $system->updated_at,
                        'delivered_at' => $delivered ? ($system->provisioned_at ?? $system->updated_at) : null,
                        'notes' => $system->notes,
                        'created_at' => $system->created_at,
                        'updated_at' => $system->updated_at,
                    ],
                );

                DB::table('it_assets')->where('id', $system->it_asset_id)->update([
                    'status' => $delivered ? 'active' : 'reserved',
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_onboarding_assets');
    }
};
