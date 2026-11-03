<?php

namespace Modules\InjongePay\Console;

use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Console\Command;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\MerchantService;

/**
 * Twin of "Sync from GTBank" on the wallets screen. Without --all: only
 * merchant codes waiting on GTBank (registering, pending, an update or a
 * closure not applied yet).
 */
class SyncMerchantsCommand extends Command
{
    protected $signature = 'injongepay:sync-merchants {--all : Every merchant code, not only those waiting on GTBank}';

    protected $description = 'Ask GTBank for the status of merchant codes';

    public function handle(MerchantService $merchants): int
    {
        if (! GtbankClient::isConfigured()) {
            $this->info('GTBank is not configured.');

            return self::SUCCESS;
        }

        $wallets = self::due($this->option('all'))->get();

        foreach ($wallets as $wallet) {
            app(CurrentBusiness::class)->set($wallet->business_id);
            $result = $merchants->sync($wallet);
            $this->line("{$wallet->merchant_code}: {$wallet->bank_status} ({$result->outcome})");
        }

        $this->info($wallets->count().' merchant code(s) checked.');

        return self::SUCCESS;
    }

    public static function due(bool $all = false)
    {
        return Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereNull('internal_role')
            ->when(! $all, fn ($q) => $q->where(fn ($w) => $w->whereIn('bank_status', [Wallet::BANK_REGISTERING, Wallet::BANK_PENDING])
                ->orWhereNotNull('bank_update_pending_at')))
            ->when($all, fn ($q) => $q->whereNotIn('bank_status', [Wallet::BANK_NOT_REGISTERED, Wallet::BANK_CLOSED]))
            ->orderBy('id');
    }
}
