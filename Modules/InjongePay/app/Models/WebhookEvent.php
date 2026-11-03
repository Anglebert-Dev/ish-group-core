<?php

namespace Modules\InjongePay\Models;

use App\Libraries\UuidLibrary;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $table = 'ijp_webhook_events';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            $event->uuid ??= UuidLibrary::generate();
        });
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
