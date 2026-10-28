<?php

namespace Modules\GarmentPack\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

class CmtJob extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'garment_cmt_jobs';

    protected $fillable = ['subcontractor', 'phone', 'stage', 'stage_name', 'sent_quantity', 'rate', 'sent_on', 'due_on', 'notes'];

    protected function casts(): array
    {
        return ['sent_quantity' => 'float', 'returned_quantity' => 'float', 'rate' => 'decimal:2', 'sent_on' => 'date', 'due_on' => 'date'];
    }

    public function workOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\WorkOrder::class);
    }

    public function outstanding(): float
    {
        return max(0, round($this->sent_quantity - $this->returned_quantity, 3));
    }

    public function owed(): float
    {
        return round($this->returned_quantity * (float) $this->rate, 2);
    }
}
