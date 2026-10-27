<?php

namespace App\Console\Commands;

use App\Models\System\System;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

/**
 * php artisan core:backup            — make a backup now
 * php artisan core:backup --scheduled — only if daily backups are switched on
 */
class BackupCommand extends Command
{
    protected $signature = 'core:backup {--scheduled : Skip unless automatic backups are on}';

    protected $description = 'Back up the database and uploaded files to storage/app/backups';

    public function handle(BackupService $backups): int
    {
        if ($this->option('scheduled') && System::getProperty('backup_daily', '0') !== '1') {
            return self::SUCCESS;
        }

        $result = $backups->create();
        $result['status'] ? $this->components->info($result['message']) : $this->components->error($result['message']);

        return $result['status'] ? self::SUCCESS : self::FAILURE;
    }
}
