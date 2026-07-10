<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_request_events', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('comment');
            $table->index(['workflow_request_id', 'is_internal'], 'workflow_events_request_internal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_request_events', function (Blueprint $table) {
            $table->dropIndex('workflow_events_request_internal_idx');
            $table->dropColumn('is_internal');
        });
    }
};
