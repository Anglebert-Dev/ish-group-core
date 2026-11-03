<?php

namespace Modules\InjongePay\Console;

use Illuminate\Console\Command;
use Modules\InjongePay\Services\WebhookInbox;

class ProcessInboxCommand extends Command
{
    protected $signature = 'injongepay:process-inbox';

    protected $description = 'Process GTBank webhooks that were stored but not processed yet';

    public function handle(WebhookInbox $inbox): int
    {
        $this->info($inbox->retryPending().' webhook(s) processed.');

        return self::SUCCESS;
    }
}
