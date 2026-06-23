<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'announcement_no')) {
                $table->string('announcement_no')->nullable()->after('created_by');
            }

            if (! Schema::hasColumn('announcements', 'popup_enabled')) {
                $table->boolean('popup_enabled')->default(false)->after('is_urgent');
            }
        });

        if (Schema::hasColumn('announcements', 'announcement_no')) {
            DB::table('announcements')
                ->whereNull('announcement_no')
                ->orderBy('id')
                ->get(['id'])
                ->each(fn ($row) => DB::table('announcements')->where('id', $row->id)->update([
                    'announcement_no' => 'WDC-ANN-'.str_pad((string) $row->id, 5, '0', STR_PAD_LEFT),
                ]));
        }

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('profile_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('field');
            $table->string('current_value')->nullable();
            $table->string('requested_value')->nullable();
            $table->string('status')->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_change_requests');
        Schema::dropIfExists('announcement_reads');

        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'popup_enabled')) {
                $table->dropColumn('popup_enabled');
            }

            if (Schema::hasColumn('announcements', 'announcement_no')) {
                $table->dropColumn('announcement_no');
            }
        });
    }
};
