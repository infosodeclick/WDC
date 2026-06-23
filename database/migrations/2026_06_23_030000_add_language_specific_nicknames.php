<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (! Schema::hasColumn('employees', 'english_nickname')) {
                    $table->string('english_nickname')->nullable()->after('nickname');
                }

                if (! Schema::hasColumn('employees', 'thai_nickname')) {
                    $table->string('thai_nickname')->nullable()->after('english_nickname');
                }
            });

            if (Schema::hasColumn('employees', 'thai_nickname') && Schema::hasColumn('employees', 'nickname')) {
                DB::table('employees')
                    ->whereNull('thai_nickname')
                    ->whereNotNull('nickname')
                    ->update(['thai_nickname' => DB::raw('nickname')]);
            }
        }

        if (Schema::hasTable('employee_directory_entries')) {
            Schema::table('employee_directory_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_directory_entries', 'english_nickname')) {
                    $table->string('english_nickname')->nullable()->after('nickname');
                }

                if (! Schema::hasColumn('employee_directory_entries', 'thai_nickname')) {
                    $table->string('thai_nickname')->nullable()->after('english_nickname');
                }
            });

            if (Schema::hasColumn('employee_directory_entries', 'thai_nickname') && Schema::hasColumn('employee_directory_entries', 'nickname')) {
                DB::table('employee_directory_entries')
                    ->whereNull('thai_nickname')
                    ->whereNotNull('nickname')
                    ->update(['thai_nickname' => DB::raw('nickname')]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_directory_entries')) {
            Schema::table('employee_directory_entries', function (Blueprint $table) {
                if (Schema::hasColumn('employee_directory_entries', 'thai_nickname')) {
                    $table->dropColumn('thai_nickname');
                }

                if (Schema::hasColumn('employee_directory_entries', 'english_nickname')) {
                    $table->dropColumn('english_nickname');
                }
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (Schema::hasColumn('employees', 'thai_nickname')) {
                    $table->dropColumn('thai_nickname');
                }

                if (Schema::hasColumn('employees', 'english_nickname')) {
                    $table->dropColumn('english_nickname');
                }
            });
        }
    }
};
