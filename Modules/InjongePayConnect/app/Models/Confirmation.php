<?php

namespace Modules\InjongePayConnect\Models;

use App\Libraries\UuidLibrary;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\InjongePay\Models\Payment;

class Confirmation extends Model
{
    use BelongsToBusiness, LogsActivity;

    public const CONFIRMED = 'CONFIRMED';

    public const UNCONFIRMED = 'UNCONFIRMED';

    protected $table = 'ijpc_confirmations';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->uuid ??= UuidLibrary::generate());
    }

    protected function casts(): array
    {
        return ['is_partial' => 'boolean', 'invoice_total' => 'decimal:2', 'balance_after' => 'decimal:2', 'confirmed_at' => 'datetime', 'unconfirmed_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class)->withoutGlobalScopes();
    }
}
