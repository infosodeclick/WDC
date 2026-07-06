<?php

namespace App\Console\Commands;

use App\Services\DirectoryUserSyncService;
use Illuminate\Console\Command;

class SyncDirectoryUsers extends Command
{
    protected $signature = 'wdc:sync-directory-users
        {file : Path to WDC.xlsx}
        {--commit : Write changes to database. Without this option the command rolls back after reporting}
        {--default-password=Wdc@2026 : Default password for newly created WDC login accounts}';

    protected $description = 'Create and link WDC login users from current employee directory entries using employee codes from an Excel file.';

    public function handle(DirectoryUserSyncService $syncService): int
    {
        $stats = $syncService->syncFromXlsx(
            (string) $this->argument('file'),
            (bool) $this->option('commit'),
            (string) $this->option('default-password'),
        );

        $this->info($stats['dry_run'] ? 'Dry run complete. No database changes were kept.' : 'Directory user sync committed.');
        $this->table(
            ['Metric', 'Count'],
            collect($stats)
                ->except(['dry_run', 'samples'])
                ->map(fn ($value, $key) => [$key, is_array($value) ? implode(', ', $value) : $value])
                ->values()
                ->all(),
        );

        if ($stats['samples'] !== []) {
            $this->line('Samples:');
            foreach ($stats['samples'] as $sample) {
                $this->line("- {$sample}");
            }
        }

        return self::SUCCESS;
    }
}
