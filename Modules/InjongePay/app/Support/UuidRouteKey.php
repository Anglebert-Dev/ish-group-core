<?php

namespace Modules\InjongePay\Support;

use Illuminate\Support\Str;

/** Route key is the public uuid; anything that isn't one is not found. */
trait UuidRouteKey
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return $field === 'uuid' && ! Str::isUuid((string) $value) ? $query->whereRaw('1 = 0') : parent::resolveRouteBindingQuery($query, $value, $field);
    }
}
