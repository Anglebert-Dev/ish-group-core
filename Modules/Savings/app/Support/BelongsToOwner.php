<?php

namespace Modules\Savings\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Each user sees only their own finances: scoped to the current owner and
 * stamped on create (like BelongsToBusiness, one level further).
 */
trait BelongsToOwner
{
    public static function bootBelongsToOwner(): void
    {
        static::addGlobalScope('owner', function (Builder $query) {
            $ownerId = app(CurrentOwner::class)->id();

            if ($ownerId !== null) {
                $query->where($query->getModel()->qualifyColumn('owner_id'), $ownerId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->owner_id)) {
                $model->owner_id = app(CurrentOwner::class)->id();
            }
        });
    }
}
