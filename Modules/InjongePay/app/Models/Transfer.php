<?php

namespace Modules\InjongePay\Models;

use App\Libraries\UuidLibrary;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    use BelongsToBusiness, \Modules\InjongePay\Support\UuidRouteKey;

    protected $table = 'ijp_transfers';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->uuid ??= UuidLibrary::generate());
    }

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }

    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }
}
