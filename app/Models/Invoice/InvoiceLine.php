<?php

namespace App\Models\Invoice;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends BaseModel
{
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
