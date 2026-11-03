<?php

namespace Modules\InjongePay\Console;

use Illuminate\Console\Command;
use Modules\InjongePay\Services\Approvals;

/** Requests no second person approved within 24 hours stop waiting. */
class ExpireApprovalsCommand extends Command
{
    protected $signature = 'injongepay:expire-approvals';

    protected $description = 'Expire money requests that waited too long for a second approval';

    public function handle(Approvals $approvals): int
    {
        $this->info($approvals->expireOverdue().' request(s) expired.');

        return self::SUCCESS;
    }
}
