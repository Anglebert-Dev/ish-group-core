<?php

namespace Modules\InjongePay\Console;

use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Console\Command;
use Modules\InjongePay\Jobs\SubmitCashout;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Services\CashoutService;

/**
 * Twin of "Check status now". Asks GTBank about every open cash-out that is
 * due, and sends the ones still queued (a lost job).
 */
class CheckCashoutsCommand extends Command
{
    protected $signature = 'injongepay:check-cashouts';

    protected $description = 'Ask GTBank for the status of open cash-outs';

    public function handle(CashoutService $cashouts): int
    {
        foreach (Cashout::query()->withoutGlobalScopes()->where('status', Cashout::QUEUED)->where('created_at', '<', now()->subMinutes(2))->get() as $queued) {
            SubmitCashout::dispatch($queued->id);
        }

        $due = Cashout::query()->withoutGlobalScopes()->whereIn('status', [Cashout::SUBMITTING, Cashout::SUBMITTED, Cashout::UNKNOWN, Cashout::NEEDS_REVIEW])
            ->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))->orderBy('id')->limit(100)->get();

        foreach ($due as $cashout) {
            app(CurrentBusiness::class)->set($cashout->business_id);
            $this->line($cashout->reference.': '.$cashouts->check($cashout));
        }

        return self::SUCCESS;
    }
}
