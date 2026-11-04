<?php

namespace Modules\InjongePay\Console;

use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Console\Command;
use Modules\InjongePay\Gtbank\BankResult;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\MerchantService;

/**
 * Twin of "Sync from GTBank" on the wallets screen. Without --all: merchant
 * codes waiting on GTBank (registering, pending, an update or a closure not
 * applied yet, a "no record" to confirm), then live codes not checked for a
 * while, so a code GTBank removes is noticed the same day.
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
        $routine = $this->option('all') ? collect() : self::stale()->whereNotIn('id', $wallets->pluck('id'))->limit(self::PER_RUN - $wallets->count())->get();
        $checked = 0;

        self::eachUntilNoAnswer($wallets->concat($routine), function (Wallet $wallet) use ($merchants, &$checked) {
            $checked++;

            return $this->check($merchants, $wallet);
        });

        $this->info($checked.' merchant code(s) checked.');

        return self::SUCCESS;
    }

    private function check(MerchantService $merchants, Wallet $wallet): BankResult
    {
        app(CurrentBusiness::class)->set($wallet->business_id);
        $result = $merchants->sync($wallet);
        $this->line("{$wallet->merchant_code}: {$wallet->bank_status} ({$result->outcome})");

        return $result;
    }

    /** GTBank isn't answering: leave after this many in a row and come back on the next turn. */
    public const LEAVE_AFTER_NO_ANSWERS = 3;

    /** @param callable(Wallet): BankResult $check */
    public static function eachUntilNoAnswer(iterable $wallets, callable $check): void
    {
        $noAnswers = 0;

        foreach ($wallets as $wallet) {
            $noAnswers = $check($wallet)->unknown() ? $noAnswers + 1 : 0;

            if ($noAnswers >= self::LEAVE_AFTER_NO_ANSWERS) {
                return;
            }
        }
    }

    /** Per run at most this many lookups, oldest checked first, so many new codes never flood GTBank. */
    public const PER_RUN = 100;

    public const CHECK_LIVE_EVERY_HOURS = 6;

    public static function due(bool $all = false)
    {
        return Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereNull('internal_role')
            ->when(! $all, fn ($q) => $q->where(fn ($w) => $w->whereIn('bank_status', [Wallet::BANK_REGISTERING, Wallet::BANK_PENDING])
                ->orWhereNotNull('bank_update_pending_at')->orWhereNotNull('bank_missing_since'))
                ->where(fn ($w) => $w->whereNull('bank_synced_at')->orWhere('bank_synced_at', '<', now()->subMinutes(10))))
            ->when($all, fn ($q) => $q->whereNotIn('bank_status', [Wallet::BANK_NOT_REGISTERED, Wallet::BANK_CLOSED]))
            ->orderByRaw('bank_synced_at IS NOT NULL')->orderBy('bank_synced_at')->orderBy('id')
            ->when(! $all, fn ($q) => $q->limit(self::PER_RUN));
    }

    /** Live codes GTBank hasn't been asked about lately, oldest first. */
    public static function stale()
    {
        return Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereNull('internal_role')
            ->whereIn('bank_status', [Wallet::BANK_ACTIVE, Wallet::BANK_INACTIVE])
            ->where(fn ($q) => $q->whereNull('bank_synced_at')->orWhere('bank_synced_at', '<', now()->subHours(self::CHECK_LIVE_EVERY_HOURS)))
            ->orderByRaw('bank_synced_at IS NOT NULL')->orderBy('bank_synced_at')->orderBy('id');
    }
}
