<?php

namespace Modules\InjongePay\Services;

use Modules\InjongePay\Models\Approval;

/** Runs an approved request with the service that owns its operation. */
class Executors
{
    public function run(Approval $approval): mixed
    {
        return match ($approval->operation) {
            'transfer' => app(TransferService::class)->execute($approval),
            'cashout' => app(CashoutService::class)->start($approval),
        };
    }
}
