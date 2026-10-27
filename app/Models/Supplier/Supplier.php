<?php

namespace App\Models\Supplier;

use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Models\BaseModel;

class Supplier extends BaseModel
{
    protected $fillable = [
        'name',
        'type',
        'contact_name',
        'phone',
        'email',
        'tin',
        'address',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => SupplierType::class,
            'status' => SupplierStatus::class,
        ];
    }
}
