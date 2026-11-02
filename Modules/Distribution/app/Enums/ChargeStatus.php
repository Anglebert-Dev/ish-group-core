<?php

namespace Modules\Distribution\Enums;

enum ChargeStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('distribution::distribution.charge_status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Unpaid => 'warning',
            self::Cancelled => 'muted',
        };
    }
}
