<?php

namespace Modules\InjongePay\Models;

use App\Libraries\UuidLibrary;
use App\Models\Business\Business;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationItem extends Model
{
    use LogsActivity;

    public const OPEN = 'open';

    public const RESOLVED = 'resolved';

    protected $table = 'ijp_reconciliation_items';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $row) => $row->uuid ??= UuidLibrary::generate());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return ['details' => 'array', 'amount' => 'integer', 'resolved_at' => 'datetime'];
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
