<?php

namespace Modules\InjongePay\Models;

use App\Libraries\UuidLibrary;
use App\Models\Business\Business;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnallocatedPayment extends Model
{
    use LogsActivity;

    public const OPEN = 'open';

    public const ALLOCATED = 'allocated';

    public const SETTLED_OUTSIDE = 'settled_outside';

    protected $table = 'ijp_unallocated_payments';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $row) {
            $row->uuid ??= UuidLibrary::generate();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'amount' => 'integer', 'resolved_at' => 'datetime'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
