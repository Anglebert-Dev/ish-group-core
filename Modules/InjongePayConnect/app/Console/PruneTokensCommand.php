<?php

namespace Modules\InjongePayConnect\Console;

use Illuminate\Console\Command;
use Modules\InjongePayConnect\Services\Credentials;

class PruneTokensCommand extends Command
{
    protected $signature = 'injongepayconnect:prune-tokens';

    protected $description = 'Delete partner tokens that expired more than a day ago';

    public function handle(Credentials $credentials): int
    {
        $this->info($credentials->prune().' token(s) deleted.');

        return self::SUCCESS;
    }
}
