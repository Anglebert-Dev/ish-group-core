<?php

namespace App\Models\ActivityLog;

use App\Enums\ActivityLogAction;
use App\Enums\ActivityLogStatus;
use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = [
        'status',
        'business_id',
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

    /**
     * Core actions are ActivityLogAction cases; modules may log their own
     * (e.g. "sale_completed"), so the raw value is kept as a string.
     */
    public function actionEnum(): ?ActivityLogAction
    {
        return ActivityLogAction::tryFrom((string) $this->getRawOriginal('action', $this->attributes['action'] ?? ''));
    }

    public function actionValue(): string
    {
        return (string) ($this->attributes['action'] ?? '');
    }

    public function actionLabel(): string
    {
        $value = $this->actionValue();

        if ($enum = $this->actionEnum()) {
            return $enum->label();
        }

        // Module actions: translation "<module>::audit.<action>" if present, else readable text.
        foreach (\App\Services\ActivityLog\ActivityLogService::customLabels() as $key) {
            if (trans()->has($key.'.'.$value)) {
                return __($key.'.'.$value);
            }
        }

        return \Illuminate\Support\Str::headline($value);
    }

    public function actionColor(): string
    {
        return $this->actionEnum()?->color() ?? 'brand';
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
