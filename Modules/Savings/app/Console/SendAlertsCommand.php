<?php

namespace Modules\Savings\Console;

use App\Enums\NotificationType;
use App\Models\Business\Business;
use App\Services\Module\ModuleManager;
use App\Services\Notification\NotificationService;
use App\Support\Settings\BusinessSettings;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Expected;
use Modules\Savings\Models\Goal;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\RecurringRule;
use Modules\Savings\Services\Budgets;
use Modules\Savings\Services\Forecast;
use Modules\Savings\Services\Obligations;
use Modules\Savings\Services\Periods;
use Modules\Savings\Services\Planning;
use Modules\Savings\Support\Money;

/**
 * Daily: refresh recurring events and send the alerts each business switched on.
 * Each alert is sent once (remembered in sav_alerts_sent).
 */
class SendAlertsCommand extends Command
{
    protected $signature = 'savings:alerts';

    protected $description = 'Generate recurring events and send finance alerts';

    public function handle(ModuleManager $modules): int
    {
        if (! $modules->isActive('Savings')) {
            return self::SUCCESS;
        }

        $sent = 0;
        foreach (Business::query()->where('is_active', true)->get() as $business) {
            if (! $business->canUseModule('Savings')) {
                continue;
            }
            app(CurrentBusiness::class)->set($business);
            app()->forgetScopedInstances();
            app(CurrentBusiness::class)->set($business);
            $sent += $this->forBusiness($business);
        }

        $this->info("Finance alerts sent: {$sent}");

        return self::SUCCESS;
    }

    private function forBusiness(Business $business): int
    {
        $planning = app(Planning::class);
        RecurringRule::query()->where('is_active', true)->get()->each(fn ($rule) => $planning->regenerate($rule));

        $on = array_merge(config('savings.alerts'), (array) app(BusinessSettings::class)->get('savings.alerts', []));
        $soon = now()->addDays((int) config('savings.upcoming_days', 3))->toDateString();
        $today = now()->toDateString();
        $alerts = [];

        if ($on['upcoming_bills']) {
            foreach (Expected::query()->where('status', 'expected')->whereIn('kind', ['expense', 'repayment_out'])->whereDate('expected_date', '>=', $today)->whereDate('expected_date', '<=', $soon)->get() as $e) {
                $alerts['bill:'.$e->uuid] = __('savings::savings.alert_bill', ['name' => $e->description, 'amount' => Money::format($e->amount), 'date' => $e->expected_date->format('d/m')]);
            }
        }
        if ($on['late_income']) {
            foreach (Expected::query()->where('status', 'expected')->where('kind', 'income')->whereDate('expected_date', '<', $today)->get() as $e) {
                $alerts['late:'.$e->uuid] = __('savings::savings.alert_late_income', ['name' => $e->description, 'amount' => Money::format($e->amount), 'date' => $e->expected_date->format('d/m')]);
            }
        }
        if ($on['overdue_obligations']) {
            $service = app(Obligations::class);
            foreach (Obligation::query()->whereNotIn('status', ['written_off', 'disputed'])->whereDate('due_date', '<', $today)->get() as $o) {
                if ($service->outstanding($o) > 0) {
                    $alerts['overdue:'.$o->uuid.':'.$o->due_date->toDateString()] = __('savings::savings.alert_overdue', ['name' => $o->counterparty ?: $o->description, 'amount' => Money::format($service->outstanding($o), $o->currency)]);
                }
            }
        }
        if ($on['budget_thresholds']) {
            [$start, $end] = app(Periods::class)->current();
            foreach (app(Budgets::class)->summary($start, $end) as $row) {
                foreach ([100, 80] as $level) {
                    if ($row['percent'] !== null && $row['percent'] >= $level) {
                        $alerts["budget:{$row['category']->id}:{$start->toDateString()}:{$level}"] = __('savings::savings.alert_budget', ['name' => $row['category']->name, 'percent' => $row['percent']]);
                        break;
                    }
                }
            }
        }
        if ($on['missed_contributions']) {
            foreach (Expected::query()->where('status', 'expected')->where('kind', 'contribution')->whereDate('expected_date', '<', $today)->get() as $e) {
                $alerts['missed:'.$e->uuid] = __('savings::savings.alert_missed', ['name' => $e->description, 'date' => $e->expected_date->format('d/m')]);
            }
        }
        if ($on['shortfalls']) {
            $accounts = Account::query()->where('status', 'active')->where('is_liquid', true)->get();
            foreach (app(Forecast::class)->run($accounts, 30) as $row) {
                if ($row['shortfall']) {
                    $alerts['shortfall:'.$row['account']->uuid.':'.$row['shortfall']] = __('savings::savings.alert_shortfall', ['account' => $row['account']->name, 'date' => \Illuminate\Support\Carbon::parse($row['shortfall'])->format('d/m')]);
                }
            }
        }

        $sent = 0;
        foreach ($alerts as $key => $message) {
            $inserted = DB::table('sav_alerts_sent')->insertOrIgnore(['business_id' => $business->id, 'key' => mb_substr($key, 0, 191), 'sent_at' => now()]);
            if ($inserted) {
                app(NotificationService::class)->notifyByPermission('list savings.overview', __('savings::savings.alert_title'), $message, NotificationType::Info, route('savings.overview'), null, $business->id);
                $sent++;
            }
        }

        return $sent;
    }
}
