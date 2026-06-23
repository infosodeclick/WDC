<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_room_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('room_name');
            $table->string('title');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedInteger('attendees')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('submitted');
            $table->timestamps();

            $table->index(['room_name', 'start_at']);
            $table->index(['user_id', 'start_at']);
            $table->index(['status', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_room_bookings');
    }
};
