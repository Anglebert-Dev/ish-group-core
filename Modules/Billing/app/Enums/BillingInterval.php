<?php

namespace Modules\Billing\Enums;

use Carbon\CarbonInterface;

enum BillingInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function addTo(CarbonInterface $date, int $count): CarbonInterface
    {
        return match ($this) {
            self::Day => $date->copy()->addDays($count),
            self::Week => $date->copy()->addWeeks($count),
            self::Month => $date->copy()->addMonthsNoOverflow($count),
            self::Year => $date->copy()->addYearsNoOverflow($count),
        };
    }

    /** Approximate days, for comparing prices across intervals. */
    public function days(): float
    {
        return match ($this) {
            self::Day => 1,
            self::Week => 7,
            self::Month => 365 / 12,
            self::Year => 365,
        };
    }

    public function label(int $count = 1): string
    {
        return trans_choice('billing::billing.interval_'.$this->value, $count, ['count' => $count]);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $i) => [$i->value => __('billing::billing.interval_name_'.$i->value)])->all();
    }
}
