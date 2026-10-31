<?php

namespace Modules\Distribution\Enums;

enum BuildStatus: string
{
    case Queued = 'queued';
    case Building = 'building';
    case Ready = 'ready';
    case Failed = 'failed';
    case Expired = 'expired';   // file deleted after its download window

    public function label(): string
    {
        return __('distribution::distribution.build_status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Ready => 'success',
            self::Queued, self::Building => 'brand',
            self::Failed => 'danger',
            self::Expired => 'muted',
        };
    }
}
