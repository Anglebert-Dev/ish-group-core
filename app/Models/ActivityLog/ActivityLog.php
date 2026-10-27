<?php

namespace App\Models\ActivityLog;

use App\Enums\ActivityLogAction;
use App\Enums\ActivityLogStatus;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends BaseModel
{
    protected $fillable = [
        'status',
        'user_id',
        'actor_name',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActivityLogStatus::class,
            'action' => ActivityLogAction::class,
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actorName(): string
    {
        return $this->actor_name
            ?: $this->user?->name
            ?: __('body.system');
    }

    public function subjectName(): string
    {
        return $this->subject_label
            ?: class_basename((string) $this->subject_type).' #'.$this->subject_id;
    }
}
