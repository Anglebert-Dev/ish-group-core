<?php

namespace App\Models\GeneralLedger;

use App\Models\BaseModel;
use App\Models\ChartOfAccount\ChartOfAccount;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralLedgerLine extends BaseModel
{
    protected $fillable = [
        'general_ledger_id',
        'chart_of_account_id',
        'debit',
        'credit',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(GeneralLedger::class, 'general_ledger_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
