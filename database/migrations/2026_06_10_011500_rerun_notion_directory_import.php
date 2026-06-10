<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('portal:import-notion-directory');
        Log::info('Notion directory import rerun completed.', [
            'output' => trim(Artisan::output()),
        ]);
    }

    public function down(): void
    {
        //
    }
};
