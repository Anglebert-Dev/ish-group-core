<?php

namespace Modules\InjongePay\Models;

use App\Libraries\UuidLibrary;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    use BelongsToBusiness, LogsActivity, \Modules\InjongePay\Support\UuidRouteKey;

    public const AWAITING = 'awaiting_approval';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const EXECUTED = 'executed';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    protected $table = 'ijp_approvals';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $a) => $a->uuid ??= UuidLibrary::generate());
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'checks' => 'array', 'amount' => 'integer', 'approved_at' => 'datetime', 'executed_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
