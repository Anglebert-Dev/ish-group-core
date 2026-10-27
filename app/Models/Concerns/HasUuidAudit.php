<?php

namespace App\Models\Concerns;

use App\Libraries\UuidLibrary;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shared by BaseModel and by Authenticatable models (User, Customer) that
 * can't extend BaseModel directly since they must extend Authenticatable.
 */
trait HasUuidAudit
{
    use LogsActivity, SoftDeletes;

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

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->update_by = auth()->id();
            }
        });
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
