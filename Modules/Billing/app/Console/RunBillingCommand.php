<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Modules\Billing\Services\SubscriptionService;

/**
 * Daily: renewal invoices, reminders, past due → grace → suspended,
 * cancellations at period end. Scheduled by the Billing module.
 */
class RunBillingCommand extends Command
{
    protected $signature = 'billing:run';

    protected $description = 'Advance subscriptions: renewal invoices, reminders, grace periods and suspensions';

    public function handle(SubscriptionService $subscriptions): int
    {
        $counts = $subscriptions->runLifecycle();

        $this->table(['Step', 'Count'], collect($counts)->map(fn ($n, $step) => [$step, $n])->values()->all());

        return self::SUCCESS;
    }
}
