<?php

namespace App\Models\Notification;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends BaseModel
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'action_url',
        'subject_type',
        'subject_id',
        'status',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'status' => NotificationStatus::class,
            'read_at' => 'datetime',
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

    public function isUnread(): bool
    {
        return $this->status === NotificationStatus::Unread;
    }
}
