<?php

namespace App\Models\CorporateCustomer;

use App\Models\BaseModel;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateContact extends BaseModel
{
    protected $fillable = [
        'corporate_customer_id',
        'customer_id',
        'type',
        'name',
        'email',
        'phone',
    ];

    public function corporateCustomer(): BelongsTo
    {
        return $this->belongsTo(CorporateCustomer::class);
    }

    /**
     * Set only when this contact is a self-registered Customer login,
     * rather than a plain contact name/email/phone entered by staff.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
