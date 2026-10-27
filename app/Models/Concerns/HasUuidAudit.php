<?php

namespace App\Models\Concerns;

use App\Libraries\UuidLibrary;
use App\Models\ActivityLog\ActivityLog;
use App\Models\Notification\Notification;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Shared by BaseModel and by Authenticatable models (User, Customer) that
 * can't extend BaseModel directly since they must extend Authenticatable.
 */
trait HasUuidAudit
{
    use SoftDeletes;

    /** @var array<int, array<string, mixed>> */
    private static array $activityOld = [];

    public static function bootHasUuidAudit(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidLibrary::generate();
            }

            if (auth()->check()) {
                $model->insert_by = auth()->id();
                $model->update_by = auth()->id();
            }
        });

        static::created(function ($model) {
            self::recordActivity($model, 'created');
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->update_by = auth()->id();
            }

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

        static::deleted(function ($model) {
            self::recordActivity($model, 'deleted');
        });
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

    /**
     * Route model binding uses the public UUID, not the internal auto-increment id.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Stamp delete_by in the same query that performs the soft delete,
     * so it lands alongside deleted_at instead of requiring a second write.
     */
    protected function runSoftDelete(): void
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();
        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;
            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        if (auth()->check()) {
            $this->delete_by = auth()->id();
            $columns['delete_by'] = auth()->id();
        }

        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('trashed', false);
    }
}
