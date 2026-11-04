<?php

namespace Modules\InjongePay\Console;

use Illuminate\Console\Command;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\BankMirror;

/**
 * Twin of "Read GTBank's list now". One call for every merchant code
 * (today, and yesterday too until 02:00 so late transactions are seen).
 */
class MirrorCommand extends Command
{
    protected $signature = 'injongepay:mirror {--days=0 : Also read this many days back}';

    protected $description = "Read GTBank's transaction list and make our books agree with it";

    public function handle(BankMirror $mirror): int
    {
        if (! GtbankClient::isConfigured()) {
            return self::SUCCESS;
        }

        $back = max((int) $this->option('days'), now()->hour < 2 ? 1 : 0);
        $summary = $mirror->pullAll(today()->subDays($back), today());

        $this->line($summary === null ? 'no answer from GTBank' : json_encode($summary));

        return $summary === null ? self::FAILURE : self::SUCCESS;
    }

    public static function wallets()
    {
        return Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereNull('internal_role')
            ->whereIn('bank_status', [Wallet::BANK_ACTIVE, Wallet::BANK_INACTIVE, Wallet::BANK_CLOSED])->orderBy('id')->get();
    }
}
