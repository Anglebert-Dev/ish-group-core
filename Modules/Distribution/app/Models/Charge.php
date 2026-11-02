<?php

namespace Modules\Distribution\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Distribution\Enums\ChargeStatus;

/**
 * A licence or support charge to a self-hosting customer. Platform-level.
 */
class Charge extends BaseModel
{
    public const LICENCE = 'licence';

    public const SUPPORT = 'support';

    public const KINDS = [self::LICENCE, self::SUPPORT];

    public const METHODS = ['bank', 'mobile_money', 'cash', 'card', 'other'];

    protected $table = 'distribution_charges';

    protected $fillable = [
        'kind', 'description', 'modules', 'amount', 'period_from', 'period_until', 'issued_on', 'due_on', 'note',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'amount' => 'decimal:2',
            'status' => ChargeStatus::class,
            'period_from' => 'date',
            'period_until' => 'date',
            'issued_on' => 'date',
            'due_on' => 'date',
            'paid_on' => 'date',
            'emailed_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isOpen(): bool
    {
        return $this->status === ChargeStatus::Unpaid;
    }
}
