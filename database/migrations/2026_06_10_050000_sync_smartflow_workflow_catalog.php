<?php

use App\Services\SmartflowWorkflowCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->string('external_step_id')->nullable()->after('workflow_template_id');
            $table->string('action_label')->nullable()->after('name');
            $table->string('branch_label')->nullable()->after('condition_label');
            $table->json('metadata')->nullable()->after('branch_label');
            $table->index(['workflow_template_id', 'external_step_id'], 'workflow_steps_template_external_idx');
        });

        app(SmartflowWorkflowCatalog::class)->sync();
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropIndex('workflow_steps_template_external_idx');
            $table->dropColumn([
                'external_step_id',
                'action_label',
                'branch_label',
                'metadata',
            ]);
        });
    }
};
