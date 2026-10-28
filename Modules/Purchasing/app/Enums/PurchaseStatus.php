<?php

namespace Modules\Purchasing\Enums;

enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case PartlyReceived = 'partly_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('purchasing::purchasing.status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Ordered => 'brand',
            self::PartlyReceived => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function canReceive(): bool
    {
        return in_array($this, [self::Ordered, self::PartlyReceived], true);
    }
}
