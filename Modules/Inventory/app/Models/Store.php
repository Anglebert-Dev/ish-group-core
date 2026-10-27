<?php

namespace Modules\Inventory\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

/**
 * A place stock is kept (shop, warehouse, workshop). Each business has a default one.
 */
class Store extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'inventory_stores';

    protected $fillable = ['name', 'code', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
