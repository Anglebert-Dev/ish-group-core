<?php

namespace Modules\InjongePay\Console;

use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Console\Command;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\BankMirror;

/** Twin of "Read GTBank's list now" on the reconciliation screen. */
class MirrorCommand extends Command
{
    protected $signature = 'injongepay:mirror {--hours=3 : How far back to read}';

    protected $description = "Read GTBank's transaction list and make our books agree with it";

    public function handle(BankMirror $mirror): int
    {
        if (! GtbankClient::isConfigured()) {
            return self::SUCCESS;
        }

        $from = now()->subHours((int) $this->option('hours'));

        foreach (self::wallets() as $wallet) {
            app(CurrentBusiness::class)->set($wallet->business_id);
            $summary = $mirror->pull($wallet, $from, now());
            $this->line($wallet->merchant_code.': '.($summary === null ? 'no answer from GTBank' : json_encode($summary)));
        }

        return self::SUCCESS;
    }

    public static function wallets()
    {
        return Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereNull('internal_role')
            ->whereIn('bank_status', [Wallet::BANK_ACTIVE, Wallet::BANK_INACTIVE, Wallet::BANK_CLOSED])->orderBy('id')->get();
    }
}
