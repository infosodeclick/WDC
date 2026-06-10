<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SmartflowCsvImporter;
use Illuminate\Console\Command;

class ImportSmartflowWorkflows extends Command
{
    protected $signature = 'portal:import-smartflow
        {path : Path to a CSV export from SmartFlow}
        {--default-requester=EMP09999 : Employee code used when a row cannot be matched to a WDC user}
        {--dry-run : Parse and validate the CSV without saving changes}';

    protected $description = 'Import SmartFlow workflow/document export rows into WDC workflow requests.';

    public function handle(SmartflowCsvImporter $importer): int
    {
        $defaultRequester = User::where('employee_code', (string) $this->option('default-requester'))->first()
            ?: User::whereHas('role', fn ($query) => $query->whereIn('slug', ['super_admin', 'admin']))->first();

        if (! $defaultRequester) {
            $this->error('Cannot find a default requester user.');

            return self::FAILURE;
        }

        $stats = $importer->import((string) $this->argument('path'), $defaultRequester, [
            'default_requester' => $defaultRequester,
            'dry_run' => (bool) $this->option('dry-run'),
        ]);

        $this->info(($stats['dry_run'] ? 'Dry run parsed' : 'Imported')." {$stats['total']} SmartFlow rows.");
        $this->line("Created: {$stats['created']}");
        $this->line("Updated: {$stats['updated']}");
        $this->line("Skipped: {$stats['skipped']}");
        $this->line("Attachments: {$stats['attachments']}");

        foreach (array_slice($stats['errors'], 0, 10) as $error) {
            $this->warn($error);
        }

        return $stats['skipped'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
