<?php

namespace Modules\Invoicing\Enums;

enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('invoicing::invoicing.status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::PartiallyPaid => 'brand',
            self::Paid => 'success',
            self::Cancelled => 'muted',
        };
    }
}
