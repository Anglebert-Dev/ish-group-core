<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog\ActivityLog;
use App\Models\Notification\Notification;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Writes created / updated / deleted entries to the audit log. Used by
 * BaseModel (through HasUuidAudit) and by models that cannot extend it
 * (spatie Role, System settings, ...).
 */
trait LogsActivity
{
    /** @var array<int, array<string, mixed>> */
    private static array $activityOld = [];

    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => self::recordActivity($model, 'created'));

        static::updating(function ($model) {
            if (! self::skipsActivity($model)) {
                self::$activityOld[spl_object_id($model)] = $model->getOriginal();
            }
        });

        static::updated(function ($model) {
            $id = spl_object_id($model);
            $old = self::$activityOld[$id] ?? [];
            unset(self::$activityOld[$id]);
            self::recordActivity($model, 'updated', $old);
        });

        static::deleted(fn ($model) => self::recordActivity($model, 'deleted'));
    }

    private static function skipsActivity(mixed $model): bool
    {
        return $model instanceof ActivityLog || $model instanceof Notification;
    }

    private static function recordActivity(mixed $model, string $action, array $old = []): void
    {
        if (self::skipsActivity($model)) {
            return;
        }

        try {
            if (! Schema::hasTable('activity_logs')) {
                return;
            }

            app(ActivityLogService::class)->record($model, $action, $old);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
