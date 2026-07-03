<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        try {
            Artisan::call('portal:import-notion-directory', [
                '--limit' => 1000,
                '--no-verify-ssl' => true,
            ]);

            Log::info('Notion directory import completed after employee reset.', [
                'output' => Artisan::output(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Notion directory import skipped after employee reset.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
