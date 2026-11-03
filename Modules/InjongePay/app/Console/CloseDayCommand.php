<?php

namespace Modules\InjongePay\Console;

use App\Support\Tenancy\CurrentBusiness;
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

        foreach (MirrorCommand::wallets() as $wallet) {
            app(CurrentBusiness::class)->set($wallet->business_id);
            $close = $reconciliation->closeDay($wallet, $day);
            $this->line("{$wallet->merchant_code} {$day->toDateString()}: {$close->state}");
        }

        $this->line(json_encode($reconciliation->checkBooks()));

        return self::SUCCESS;
    }
}
