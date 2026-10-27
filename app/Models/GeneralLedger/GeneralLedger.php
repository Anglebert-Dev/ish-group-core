<?php

namespace App\Models\GeneralLedger;

use App\Enums\GeneralLedgerStatus;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneralLedger extends BaseModel
{
    protected $fillable = [
        'transaction_date',
        'reference',
        'reference_type',
        'reference_id',
        'description',
        'status',
        'reversed_ledger_id',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'status' => GeneralLedgerStatus::class,
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GeneralLedgerLine::class);
    }

    public function reversedLedger(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_ledger_id');
    }
}
