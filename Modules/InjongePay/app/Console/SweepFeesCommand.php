<?php

namespace Modules\InjongePay\Console;

use Illuminate\Console\Command;
use Modules\InjongePay\Services\FeeSweep;
use Modules\InjongePay\Support\Money;

/** Twin of "Credit fees now" on Injonge Pay settings. */
class SweepFeesCommand extends Command
{
    protected $signature = 'injongepay:sweep-fees';

    protected $description = 'Credit the fees of confirmed payments to the platform wallets';

    public function handle(FeeSweep $sweep): int
    {
        $this->info(Money::format($sweep->run()).' credited.');

        return self::SUCCESS;
    }
}
