<?php

namespace Modules\Production\Enums;

enum WorkOrderStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('production::production.status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'muted',
            self::InProgress => 'brand',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Planned, self::InProgress], true);
    }
}
