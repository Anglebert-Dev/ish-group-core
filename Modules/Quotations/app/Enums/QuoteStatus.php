<?php

namespace Modules\Quotations\Enums;

enum QuoteStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Converted = 'converted';

    public function label(): string
    {
        return __('quotations::quotations.status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'brand',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Converted => 'muted',
        };
    }
}
