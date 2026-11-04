<?php

namespace Modules\InjongePay\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Services\Legacy\LegacyImport;
use Modules\InjongePay\Support\Money;

/**
 * Terminal only, on purpose: it runs once, at the switch from the old app, with
 * the old app stopped. Without --commit nothing is kept.
 */
class ImportLegacyCommand extends Command
{
    protected $signature = 'injongepay:import-legacy {--commit : Keep the result. Without it the whole import runs, is checked, and is rolled back} {--force : Don\'t ask for confirmation}';

    protected $description = 'Bring every record of the old Injonge Pay database into this app';

    public function handle(LegacyImport $import): int
    {
        $commit = (bool) $this->option('commit');

        if ($commit && ! $this->option('force') && ! $this->confirm('This writes the old data into THIS database. Is the old app stopped?')) {
            return self::FAILURE;
        }

        $this->info($commit ? 'Importing…' : 'Dry run: everything runs, then it is rolled back.');

        try {
            $result = $import->run($commit);
        } catch (InjongePayException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Check', 'Old app', 'Here', ''], collect($result['checks'])->map(fn ($c) => [$c['label'], $c['old'], $c['new'], $c['ok'] ? 'ok' : 'DIFFERENT'])->all());
        $this->table(['Imported', 'Rows'], collect($result['counts'])->map(fn ($v, $k) => [$k, $v])->values()->all());

        $this->line('Platform difference (payments − balances − money out): '.Money::toDecimal($result['platform_difference']));
        foreach ($result['attention'] as $line) {
            $this->warn('• '.$line);
        }

        $path = storage_path('logs/injongepay-import-'.now()->format('Ymd-His').'.json');
        File::put($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line("Report: {$path}");

        if (! $result['passed']) {
            $this->error('A total does not match. Nothing was kept.');

            return self::FAILURE;
        }

        $result['committed'] ? $this->info('Done. The data is in.') : $this->info('Every check passed. Nothing was kept: run again with --commit.');

        return self::SUCCESS;
    }
}
