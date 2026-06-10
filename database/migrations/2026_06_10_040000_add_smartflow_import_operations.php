<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_requests', function (Blueprint $table) {
            $table->string('external_source')->nullable()->after('legacy_reference');
            $table->string('external_record_id')->nullable()->after('external_source');
            $table->string('external_url', 1000)->nullable()->after('external_record_id');
            $table->json('external_payload')->nullable()->after('external_url');
            $table->timestamp('imported_at')->nullable()->after('external_payload');
            $table->index(['external_source', 'external_record_id'], 'workflow_requests_external_idx');
            $table->index(['smartflow_menu', 'status'], 'workflow_requests_menu_status_idx');
        });

        Schema::create('workflow_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_request_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('source_system')->default('wdc');
            $table->string('file_name');
            $table->string('file_url', 1200);
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['workflow_request_id', 'source_system'], 'workflow_attachments_request_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_request_attachments');

        Schema::table('workflow_requests', function (Blueprint $table) {
            $table->dropIndex('workflow_requests_external_idx');
            $table->dropIndex('workflow_requests_menu_status_idx');
            $table->dropColumn([
                'external_source',
                'external_record_id',
                'external_url',
                'external_payload',
                'imported_at',
            ]);
        });
    }
};
