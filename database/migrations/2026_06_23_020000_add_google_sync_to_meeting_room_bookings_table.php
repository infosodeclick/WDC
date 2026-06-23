<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_room_bookings', function (Blueprint $table) {
            $table->string('google_event_id')->nullable()->after('status');
            $table->timestamp('synced_at')->nullable()->after('google_event_id');
            $table->text('sync_error')->nullable()->after('synced_at');
            $table->timestamp('cancelled_at')->nullable()->after('sync_error');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();

            $table->index('google_event_id');
            $table->index(['status', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('meeting_room_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropIndex(['google_event_id']);
            $table->dropIndex(['status', 'cancelled_at']);
            $table->dropColumn(['google_event_id', 'synced_at', 'sync_error', 'cancelled_at']);
        });
    }
};
