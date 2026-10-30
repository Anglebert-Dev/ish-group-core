<?php

namespace Modules\Savings\Models;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Modules\Savings\Support\BelongsToOwner;
use Modules\Savings\Support\HasPublicId;

class ImportBatch extends Model
{
    use BelongsToBusiness, BelongsToOwner, HasPublicId;

    protected $table = 'sav_import_batches';

    protected $guarded = ['id', 'uuid', 'business_id', 'owner_id'];

    protected function casts(): array
    {
        return ['rows' => 'array'];
    }

}
