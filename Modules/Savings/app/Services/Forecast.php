<?php

namespace Modules\Savings\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Commitment;
use Modules\Savings\Models\Expected;
use Modules\Savings\Models\Goal;

/**
 * Cash-flow forecast: a calculated view, never posted (BR-07). Starts from
 * actual balances and applies expected events and commitments day by day.
 */
class Forecast
{
    public function __construct(protected Balances $balances)
    {
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @param  array<string, array{date?: string, amount?: int, exclude?: bool}>  $overrides  what-if by event uuid
     * @return array<int, array{account: Account, start: int, threshold: int, end: int, lowest: int, lowest_date: string, shortfall: ?string, causes: list<array>, days: array<string, int>, events: list<array>}>
     */
    public function run(Collection $accounts, int $days = 60, array $overrides = []): array
    {
        $today = now()->startOfDay();
        $until = $today->copy()->addDays($days);
        $ids = $accounts->pluck('id');
        $events = $this->events($ids->all(), $today, $until, $overrides);
        $result = [];

        foreach ($accounts as $account) {
            $balance = $this->balances->balance($account);
            $threshold = (int) ($account->minimum_balance ?? 0);
            $mine = collect($events)->filter(fn ($e) => isset($e['effects'][$account->id]))->sortBy('date')->values();
            $daily = [];
            $lowest = $balance;
            $lowestDate = $today->toDateString();
            $shortfall = null;
            $running = $balance;

            for ($day = $today->copy(); $day->lte($until); $day->addDay()) {
                $date = $day->toDateString();
                foreach ($mine->where('date', $date) as $event) {
                    $running += $event['effects'][$account->id];
                }
                $daily[$date] = $running;
                if ($running < $lowest) {
                    [$lowest, $lowestDate] = [$running, $date];
                }
                if ($shortfall === null && $running < $threshold) {
                    $shortfall = $date;
                }
            }

            // What pulls the balance down before the shortfall.
            $causes = $shortfall === null ? [] : $mine
                ->filter(fn ($e) => $e['date'] <= $shortfall && $e['effects'][$account->id] < 0)
                ->sortBy(fn ($e) => $e['effects'][$account->id])
                ->values()->all();

            $result[$account->id] = [
                'account' => $account,
                'start' => $balance,
                'threshold' => $threshold,
                'end' => $running,
                'lowest' => $lowest,
                'lowest_date' => $lowestDate,
                'shortfall' => $shortfall,
                'causes' => $causes,
                'days' => $daily,
                'events' => $mine->all(),
            ];
        }

        return $result;
    }

    /**
     * @return list<array{key: string, date: string, label: string, status: string, effects: array<int, int>, amount: int}>
     */
    private function events(array $accountIds, Carbon $today, Carbon $until, array $overrides): array
    {
        $goalAccounts = Goal::query()->pluck('account_id', 'id');
        $events = [];

        $expected = Expected::query()->where('status', 'expected')
            ->whereDate('expected_date', '<=', $until->toDateString())
            ->where(fn ($q) => $q->whereIn('account_id', $accountIds)->orWhereIn('to_account_id', $accountIds))
            ->get();

        foreach ($expected as $event) {
            $override = $overrides[$event->uuid] ?? [];
            if (! empty($override['exclude'])) {
                continue;
            }
            $date = Carbon::parse($override['date'] ?? $event->expected_date)->startOfDay();
            $late = $date->lt($today);
            $date = $late ? $today->copy() : $date;
            if ($date->gt($until)) {
                continue;
            }
            $amount = (int) ($override['amount'] ?? $event->amount);
            $to = $event->kind === 'contribution' ? ($goalAccounts[$event->goal_id] ?? $event->to_account_id) : $event->to_account_id;

            $effects = match ($event->kind) {
                'income', 'repayment_in' => [$event->account_id => $amount],
                'expense', 'repayment_out' => [$event->account_id => -$amount],
                default => array_filter([$event->account_id => -$amount] + ($to ? [$to => $amount] : [])),
            };

            $events[] = [
                'key' => $event->uuid,
                'date' => $date->toDateString(),
                'label' => $event->description,
                'kind' => $event->kind,
                'status' => $late ? 'late' : $event->confidence,
                'amount' => $amount,
                'effects' => $effects,
                'changed' => $override !== [],
            ];
        }

        foreach (Commitment::query()->where('status', 'open')->whereIn('account_id', $accountIds)->get() as $commitment) {
            $date = $commitment->due_date && $commitment->due_date->gte($today) ? $commitment->due_date->toDateString() : $today->toDateString();
            if ($date > $until->toDateString()) {
                continue;
            }
            $events[] = [
                'key' => 'commitment:'.$commitment->uuid,
                'date' => $date,
                'label' => $commitment->description,
                'kind' => 'commitment',
                'status' => 'committed',
                'amount' => $commitment->amount,
                'effects' => [$commitment->account_id => -$commitment->amount],
                'changed' => false,
            ];
        }

        return $events;
    }
}
