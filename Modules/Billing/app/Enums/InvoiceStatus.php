<?php

namespace Modules\Billing\Enums;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return __('billing::billing.invoice_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Paid => 'success',
            self::Void => 'muted',
        };
    }
}
