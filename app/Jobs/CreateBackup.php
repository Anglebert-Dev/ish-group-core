<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs a backup in the background and tells whoever asked for it.
 */
class CreateBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1800;

    public function __construct(public ?int $requestedBy = null)
    {
    }

    public function handle(BackupService $backups, NotificationService $notifications): void
    {
        $result = $backups->create();

        if ($this->requestedBy && ($user = User::query()->find($this->requestedBy))) {
            $notifications->notify(
                $user,
                $result['message'],
                null,
                $result['status'] ? NotificationType::Success : NotificationType::Warning,
                route('superadmin.backups.index', [], false),
            );
        }
    }
}
