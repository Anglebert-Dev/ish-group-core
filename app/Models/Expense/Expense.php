<?php

namespace App\Models\Expense;

use App\Enums\ExpenseStatus;
use App\Models\BaseModel;
use App\Models\ChartOfAccount\ChartOfAccount;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends BaseModel
{
    protected $fillable = [
        'expense_number',
        'expense_date',
        'amount',
        'supplier_id',
        'chart_of_account_id',
        'description',
        'paid',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'paid' => 'boolean',
            'status' => ExpenseStatus::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
