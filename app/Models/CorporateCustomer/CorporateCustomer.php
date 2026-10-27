<?php

namespace App\Models\CorporateCustomer;

use App\Enums\CorporateCustomerStatus;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorporateCustomer extends BaseModel
{
    protected $fillable = [
        'company_name',
        'tin',
        'email',
        'phone',
        'address',
        'billing_address',
        'payment_terms',
        'credit_limit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CorporateCustomerStatus::class,
            'credit_limit' => 'decimal:2',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CorporateContact::class);
    }
}
