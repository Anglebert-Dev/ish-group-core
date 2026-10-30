<?php

namespace Modules\Savings\Models;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Modules\Savings\Support\BelongsToOwner;
use Modules\Savings\Support\HasPublicId;

class Reconciliation extends Model
{
    use BelongsToBusiness, BelongsToOwner, HasPublicId;

    protected $table = 'sav_reconciliations';

    protected $guarded = ['id', 'uuid', 'business_id', 'owner_id'];

    protected function casts(): array
    {
        return ['statement_date' => 'date', 'statement_balance' => 'integer', 'ledger_balance' => 'integer', 'difference' => 'integer'];
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }
}
