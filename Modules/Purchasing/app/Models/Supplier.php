<?php

namespace Modules\Purchasing\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

class Supplier extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'tax_id', 'address', 'payment_terms_days', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'payment_terms_days' => 'integer'];
    }
}
