<?php

namespace Modules\InjongePay\Models;

use App\Libraries\UuidLibrary;
use Illuminate\Database\Eloquent\Model;

class TrustedDevice extends Model
{
    protected $table = 'ijp_trusted_devices';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected static function booted(): void
    {
        static::creating(fn (self $d) => $d->uuid ??= UuidLibrary::generate());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
