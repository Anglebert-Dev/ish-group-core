<?php

namespace App\Models\AccountAssignment;

use App\Enums\ChartOfAccountStatus;
use App\Models\BaseModel;
use App\Models\ChartOfAccount\ChartOfAccount;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAssignment extends BaseModel
{
    protected $fillable = [
        'action',
        'slot',
        'chart_of_account_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChartOfAccountStatus::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
}
