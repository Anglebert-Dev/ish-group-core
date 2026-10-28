<?php

namespace Modules\GarmentPack\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Bundle extends Model
{
    use BelongsToBusiness;

    protected $table = 'garment_bundles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float'];
    }
}
