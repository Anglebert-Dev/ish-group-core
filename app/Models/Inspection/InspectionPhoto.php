<?php

namespace App\Models\Inspection;

use App\Libraries\UploadLibrary;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionPhoto extends BaseModel
{
    protected $fillable = [
        'inspection_id',
        'file_path',
        'sort_order',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function url(): string
    {
        return UploadLibrary::url($this->file_path);
    }
}
