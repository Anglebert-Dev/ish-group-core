<?php

namespace App\Models\ChartOfAccount;

use App\Enums\AccountType;
use App\Enums\ChartOfAccountStatus;
use App\Enums\NormalBalance;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends BaseModel
{
    protected $fillable = [
        'code',
        'name',
        'account_type',
        'normal_balance',
        'parent_id',
        'is_posting',
        'current_balance',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'normal_balance' => NormalBalance::class,
            'is_posting' => 'boolean',
            'current_balance' => 'decimal:2',
            'status' => ChartOfAccountStatus::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function label(): string
    {
        return $this->code.' — '.$this->name;
    }
}
