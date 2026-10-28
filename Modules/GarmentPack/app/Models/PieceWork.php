<?php

namespace Modules\GarmentPack\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

class PieceWork extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'garment_piece_work';

    protected $fillable = ['worker_id', 'work_order_id', 'stage', 'stage_name', 'quantity', 'rate', 'amount', 'work_date'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'rate' => 'decimal:2', 'amount' => 'decimal:2', 'work_date' => 'date', 'paid_at' => 'datetime'];
    }

    public function worker(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
    }

    public function workOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Production\Models\WorkOrder::class);
    }
}
