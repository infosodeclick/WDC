<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_request_attachments', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('file_url');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_request_attachments', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_size']);
        });
    }
};
