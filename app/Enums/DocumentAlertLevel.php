<?php

namespace App\Enums;

enum DocumentAlertLevel: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Green => __('body.document_alert_ok'),
            self::Yellow => __('body.document_alert_expiring'),
            self::Red => __('body.document_alert_expired_or_urgent'),
            self::Unknown => __('body.document_alert_missing'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Green => 'success',
            self::Yellow => 'warning',
            self::Red => 'danger',
            self::Unknown => 'muted',
        };
    }

    public function isAttention(): bool
    {
        return in_array($this, [self::Yellow, self::Red], true);
    }
}
