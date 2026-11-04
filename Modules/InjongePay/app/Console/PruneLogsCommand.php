<?php

namespace Modules\InjongePay\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\InjongePay\Models\BankCall;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Services\BackgroundJobs;

/**
 * Keeps the GTBank call log from filling the disk. A call about a cash-out, or
 * one a cash-out event or a settlement account points to, is proof: it stays.
 */
class PruneLogsCommand extends Command
{
    protected $signature = 'injongepay:prune-logs';

    protected $description = 'Delete GTBank call logs older than the days set on the Background jobs page';

    public function handle(): int
    {
        $old = BankCall::query()->where('created_at', '<', now()->subDays(BackgroundJobs::logDays()))
            ->where(fn ($q) => $q->whereNull('subject_type')->orWhere('subject_type', '!=', (new Cashout)->getMorphClass()))
            ->whereNotIn('id', DB::table('ijp_cashout_events')->whereNotNull('bank_call_id')->select('bank_call_id'))
            ->whereNotIn('id', DB::table('ijp_settlement_accounts')->whereNotNull('verification_call_id')->select('verification_call_id'));

        $deleted = 0;

        do {
            $ids = (clone $old)->limit(1000)->pluck('id');
            $deleted += BankCall::query()->whereIn('id', $ids)->delete();
        } while ($ids->isNotEmpty());

        if ($deleted > 0) {
            audit('injongepay_logs_pruned', __('injongepay::injongepay.audit_logs_pruned', ['count' => $deleted]), null, ['count' => $deleted, 'days' => BackgroundJobs::logDays()]);
        }

        $this->info("{$deleted} call log(s) deleted.");

        return self::SUCCESS;
    }
}
