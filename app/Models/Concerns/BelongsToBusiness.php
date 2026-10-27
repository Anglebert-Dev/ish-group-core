<?php

namespace App\Models\Concerns;

use App\Models\Business\Business;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current business and stamps business_id on create.
 * Use it on every tenant-owned model, core or module.
 *
 * Do not put it on User: the staff guard loads the user through the same
 * query, so the scope would recurse while resolving the current business.
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query) {
            $businessId = app(CurrentBusiness::class)->id();

            if ($businessId !== null) {
                $query->where($query->getModel()->qualifyColumn('business_id'), $businessId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->business_id)) {
                $model->business_id = app(CurrentBusiness::class)->id();
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
