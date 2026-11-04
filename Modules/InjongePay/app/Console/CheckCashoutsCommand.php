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

        $noAnswers = 0;

        foreach ($due as $cashout) {
            app(CurrentBusiness::class)->set($cashout->business_id);
            $state = $cashouts->check($cashout);
            $this->line($cashout->reference.': '.$state);

            $noAnswers = $state === CashoutService::NO_ANSWER ? $noAnswers + 1 : 0;

            // GTBank isn't answering: leave now and come back on the next turn.
            if ($noAnswers >= SyncMerchantsCommand::LEAVE_AFTER_NO_ANSWERS) {
                break;
            }
        }

        // Closed ones: a late "paid" or a late reversal must be found even without a webhook.
        $since = now()->subDays(CashoutService::WATCH_CLOSED_DAYS);
        $closed = Cashout::query()->withoutGlobalScopes()
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('status', Cashout::FAILED)->where('failed_at', '>=', $since))
                ->orWhere(fn ($w) => $w->where('status', Cashout::COMPLETED)->where('completed_at', '>=', $since)))
            ->whereNotNull('next_check_at')->where('next_check_at', '<=', now())->orderBy('next_check_at')->limit(100)->get();
        $noAnswers = 0;

        foreach ($closed as $cashout) {
            app(CurrentBusiness::class)->set($cashout->business_id);
            $noAnswers = $cashouts->watchClosed($cashout) === CashoutService::NO_ANSWER ? $noAnswers + 1 : 0;

            if ($noAnswers >= SyncMerchantsCommand::LEAVE_AFTER_NO_ANSWERS) {
                break;
            }
        }

        return self::SUCCESS;
    }
}
