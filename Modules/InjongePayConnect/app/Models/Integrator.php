<?php

namespace Modules\InjongePayConnect\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integrator extends BaseModel
{
    protected $table = 'ijpc_integrators';

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }
}
