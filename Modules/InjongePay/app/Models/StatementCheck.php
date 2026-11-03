<?php

namespace Modules\InjongePay\Models;

use App\Libraries\UuidLibrary;
use Illuminate\Database\Eloquent\Model;
use Modules\InjongePay\Support\UuidRouteKey;

class StatementCheck extends Model
{
    use UuidRouteKey;

    protected $table = 'ijp_statement_checks';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $c) => $c->uuid ??= UuidLibrary::generate());
    }

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'summary' => 'array', 'unmatched' => 'array', 'agrees' => 'boolean'];
    }
}
