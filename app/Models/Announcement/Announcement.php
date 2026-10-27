<?php

namespace App\Models\Announcement;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends BaseModel
{
    protected $fillable = ['subject', 'body', 'audience', 'business_ids', 'send_email', 'send_in_app', 'recipients_count', 'sent_by'];

    protected function casts(): array
    {
        return [
            'business_ids' => 'array',
            'send_email' => 'boolean',
            'send_in_app' => 'boolean',
            'recipients_count' => 'integer',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function name(): string
    {
        return $this->subject;
    }
}
