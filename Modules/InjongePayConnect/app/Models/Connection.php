<?php

namespace Modules\InjongePayConnect\Models;

use App\Models\BaseModel;
use App\Models\Business\Business;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Connection extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'ijpc_connections';

    protected $guarded = ['id', 'uuid', 'business_id', 'client_secret_hash', 'legacy_id'];

    protected $hidden = ['client_secret_hash'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'last_used_at' => 'datetime', 'rate_limit' => 'integer'];
    }

    public function integrator(): BelongsTo
    {
        return $this->belongsTo(Integrator::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return list<string> */
    public function allowedIps(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->allowed_ips))));
    }
}
