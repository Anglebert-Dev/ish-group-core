<?php

namespace Modules\InjongePay\Models;

use App\Libraries\UuidLibrary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $table = 'ijp_bank_transactions';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $row) => $row->uuid ??= UuidLibrary::generate());
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'amount' => 'integer', 'value_date' => 'date', 'bank_created_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
