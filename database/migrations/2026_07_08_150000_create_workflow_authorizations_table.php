<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authorizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('authorized_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['authorized_user_id', 'status', 'valid_from', 'valid_until'], 'workflow_auth_lookup_index');
            $table->index(['authorizer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_authorizations');
    }
};
