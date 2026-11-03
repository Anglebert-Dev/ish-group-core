<?php

namespace Modules\InjongePay\Models;

use App\Libraries\UuidLibrary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BankCall extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ijp_bank_calls';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $call) {
            $call->uuid ??= UuidLibrary::generate();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return ['request' => 'array', 'response' => 'array', 'created_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
