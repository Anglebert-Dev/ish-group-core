<?php

namespace Modules\Quotations\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\Store;
use Modules\Quotations\Enums\QuoteStatus;
use Modules\Quotations\Enums\SampleStatus;
use Modules\SalesOrders\Models\SalesOrder;

class Quotation extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = ['customer_id', 'store_id', 'quote_date', 'valid_until', 'follow_up_date', 'is_sample', 'discount', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'sample_status' => SampleStatus::class,
            'is_sample' => 'boolean',
            'quote_date' => 'date',
            'valid_until' => 'date',
            'follow_up_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('position');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(QuotationDeposit::class)->orderBy('paid_on')->orderBy('id');
    }

    public function depositTotal(): float
    {
        return (float) $this->deposits()->whereNull('reversed_at')->sum('amount');
    }

    public function isExpired(): bool
    {
        return $this->status === QuoteStatus::Open && $this->valid_until !== null && $this->valid_until->lt(now()->startOfDay());
    }

    public function needsFollowUp(): bool
    {
        return $this->status === QuoteStatus::Open && $this->follow_up_date !== null && $this->follow_up_date->lte(now()->startOfDay());
    }
}
