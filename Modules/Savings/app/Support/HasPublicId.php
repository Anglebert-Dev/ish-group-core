<?php

namespace Modules\Savings\Support;

use App\Libraries\UuidLibrary;

/**
 * UUID for routes on models that don't extend BaseModel.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidLibrary::generate();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
