<?php

namespace Modules\Savings\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;

class Category extends BaseModel
{
    use BelongsToBusiness;

    protected $table = 'sav_categories';

    protected $guarded = ['id', 'uuid', 'business_id'];

    protected function casts(): array
    {
        return ['is_archived' => 'boolean'];
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }
}
