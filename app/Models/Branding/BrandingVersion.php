<?php

namespace App\Models\Branding;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of a business's appearance (docs/branding.md §2.2).
 */
class BrandingVersion extends BaseModel
{
    use BelongsToBusiness;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const ARCHIVED = 'archived';

    protected $fillable = ['number', 'status', 'payload', 'note'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'update_by');
    }
}
