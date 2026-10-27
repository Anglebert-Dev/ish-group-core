<?php

namespace Modules\Invoicing\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\Store;
use Modules\Invoicing\Enums\InvoiceStatus;

class SalesInvoice extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = ['customer_id', 'store_id', 'sales_order_id', 'invoice_date', 'due_date', 'discount', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'float',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'stock_issued' => 'boolean',
            'stock_returned' => 'boolean',
            'cancelled_at' => 'datetime',
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

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class, 'invoice_id')->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesPayment::class, 'invoice_id')->orderBy('paid_on')->orderBy('id');
    }

    public function balance(): float
    {
        return $this->status === InvoiceStatus::Cancelled ? 0.0 : round((float) $this->total - (float) $this->paid, 2);
    }

    public function isOverdue(): bool
    {
        return $this->balance() > 0 && $this->due_date !== null && $this->due_date->lt(now()->startOfDay());
    }
}
