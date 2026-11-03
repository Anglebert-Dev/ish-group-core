<?php

namespace Modules\InjongePay\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\InjongePay\Services\Reconciliation;

/** Twin of "Close the day" on the reconciliation screen. */
class CloseDayCommand extends Command
{
    protected $signature = 'injongepay:close-day {day? : Y-m-d, default yesterday}';

    protected $description = 'Compare each merchant code\'s day with GTBank and check the books';

    public function handle(Reconciliation $reconciliation): int
    {
        $day = Carbon::parse($this->argument('day') ?? now()->subDay()->toDateString());
        $result = $reconciliation->closeDayForAll($day);

        $this->line("{$day->toDateString()}: {$result['closed']} merchant code(s) closed".($result['unreachable'] ? ', GTBank did not answer' : ''));
        $this->line(json_encode($reconciliation->checkBooks()));

        return self::SUCCESS;
    }
}
