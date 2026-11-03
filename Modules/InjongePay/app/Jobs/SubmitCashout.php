<?php

namespace Modules\InjongePay\Jobs;

use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Services\CashoutService;

/** Sends a cash-out to GTBank once. Never retried: the status checks take over. */
class SubmitCashout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $cashoutId)
    {
    }

    public function handle(CashoutService $cashouts): void
    {
        $cashout = Cashout::query()->withoutGlobalScopes()->find($this->cashoutId);

        if ($cashout) {
            app(CurrentBusiness::class)->set($cashout->business_id);
            $cashouts->submit($cashout);
        }
    }
}
