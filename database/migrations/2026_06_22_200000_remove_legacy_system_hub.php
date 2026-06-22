<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'systems.view')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('external_system_accounts');
        Schema::dropIfExists('legacy_system_snapshots');
        Schema::dropIfExists('legacy_systems');
    }

    public function down(): void
    {
        Schema::create('legacy_systems', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('url');
            $table->string('login_method')->nullable();
            $table->text('summary')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::create('external_system_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legacy_system_id')->constrained()->cascadeOnDelete();
            $table->string('login_identifier')->nullable();
            $table->string('credential_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'legacy_system_id']);
        });

        Schema::create('legacy_system_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('source_system');
            $table->string('snapshot_type');
            $table->string('title');
            $table->string('source_url')->nullable();
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });
    }
};
