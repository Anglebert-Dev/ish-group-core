<?php

namespace Modules\Savings\Services;

use App\Support\Settings\BusinessSettings;
use Illuminate\Support\Carbon;

/**
 * Budget periods from the business's rule: monthly from any start day
 * (28 → 28th to 27th), weekly from a weekday, biweekly from an anchor date.
 * A start day past the month's end uses the month's last day.
 */
class Periods
{
    public function __construct(protected BusinessSettings $settings)
    {
    }

    public function rule(): array
    {
        return array_merge(config('savings.period'), (array) $this->settings->get('savings.period', []));
    }

    /**
     * @return array{0: Carbon, 1: Carbon} start and end (inclusive) of the period containing $date
     */
    public function for(Carbon|string $date): array
    {
        $date = Carbon::parse($date)->startOfDay();
        $rule = $this->rule();

        return match ($rule['type']) {
            'weekly' => $this->weekly($date, (int) $rule['weekday'], 7),
            'biweekly' => $this->fromAnchor($date, Carbon::parse($rule['anchor'])->startOfDay(), 14),
            default => $this->monthly($date, max(1, min(31, (int) $rule['start_day']))),
        };
    }

    public function current(): array
    {
        return $this->for(now());
    }

    public function previous(Carbon $start): array
    {
        return $this->for($start->copy()->subDay());
    }

    public function next(Carbon $end): array
    {
        return $this->for($end->copy()->addDay());
    }

    /**
     * Label like "28 Oct – 27 Nov 2026".
     */
    public function label(array $period): string
    {
        [$start, $end] = $period;

        return $start->format('d M').' – '.$end->format('d M Y');
    }

    private function monthly(Carbon $date, int $day): array
    {
        $startIn = fn (Carbon $month) => $month->copy()->startOfMonth()->day(min($day, $month->daysInMonth));
        $start = $startIn($date);
        if ($date->lt($start)) {
            $start = $startIn($date->copy()->startOfMonth()->subMonth());
        }
        $end = $startIn($start->copy()->startOfMonth()->addMonth())->subDay();

        return [$start, $end];
    }

    private function weekly(Carbon $date, int $weekday, int $length): array
    {
        $start = $date->copy();
        while ($start->dayOfWeekIso !== $weekday) {
            $start->subDay();
        }

        return [$start, $start->copy()->addDays($length - 1)];
    }

    private function fromAnchor(Carbon $date, Carbon $anchor, int $length): array
    {
        $offset = (int) floor($anchor->diffInDays($date, false) / $length);
        $start = $anchor->copy()->addDays($offset * $length);

        return [$start, $start->copy()->addDays($length - 1)];
    }
}
