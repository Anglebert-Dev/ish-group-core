<?php

namespace Modules\Quotations\Enums;

/**
 * Each state has its own label: an approved sample reads "Approved", never "Completed".
 */
enum SampleStatus: string
{
    case Requested = 'requested';
    case Sent = 'sent';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';

    public function label(): string
    {
        return __('quotations::quotations.sample_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'muted',
            self::Sent => 'brand',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Returned => 'warning',
        };
    }
}
