<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('software_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('vendor')->nullable();
            $table->string('license_type')->default('subscription');
            $table->unsignedInteger('seat_count')->default(1);
            $table->unsignedInteger('assigned_seats')->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('department')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('software_licenses');
    }
};
