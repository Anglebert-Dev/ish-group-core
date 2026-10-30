<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Savings\Http\Controllers\Concerns\UsesLedger;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Goal;
use Modules\Savings\Services\Ledger;
use Modules\Savings\Services\Planning;
use Modules\Savings\Support\Money;

class GoalController extends Controller
{
    use UsesLedger;

    public function __construct(protected Planning $planning)
    {
    }

    public function index(): View
    {
        $goals = Goal::query()->with(['account', 'allocations'])->orderBy('status')->orderBy('target_date')->get();

        return view('savings::goals.index', [
            'goals' => $goals->map(fn (Goal $g) => [
                'g' => $g, 'earmarked' => $this->planning->earmarked($g), 'funded' => $this->planning->funded($g), 'missed' => $this->planning->missedContributions($g),
            ]),
            'accounts' => Account::query()->where('status', 'active')->orderBy('name')->get(),
            'currency' => Money::baseCurrency(),
            'key' => Ledger::key(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->attempt(function () use ($request) {
            $goal = new Goal($this->validated($request) + ['status' => 'active']);
            $goal->save();
        }, __('savings::savings.goal_saved'));
    }

    public function update(Request $request, Goal $goal): RedirectResponse
    {
        return $this->attempt(fn () => $goal->fill($this->validated($request) + ['status' => $request->input('status', 'active')])->save(), __('savings::savings.goal_saved'));
    }

    public function allocate(Request $request, Goal $goal): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->planning->allocate($goal, $this->money($data['amount'], $goal->currency), $data['note'] ?? null), __('savings::savings.goal_saved'));
    }

    public function reassign(Request $request, Goal $goal): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'string'], 'amount' => ['required', 'string'], 'note' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->planning->reassign($goal, Goal::query()->where('uuid', $data['to'])->firstOrFail(), $this->money($data['amount'], $goal->currency), $data['note']), __('savings::savings.goal_saved'));
    }

    public function contribute(Request $request, Goal $goal): RedirectResponse
    {
        $data = $request->validate(['account' => ['required', 'string'], 'amount' => ['required', 'string'], 'effective_date' => ['required', 'date'], 'idempotency_key' => ['required', 'string']]);

        return $this->attempt(function () use ($data, $goal) {
            $from = Account::query()->where('uuid', $data['account'])->firstOrFail();

            return $this->planning->contribute($goal, $from, $this->money($data['amount'], $from->currency), [
                'effective_date' => $data['effective_date'], 'idempotency_key' => $data['idempotency_key'], 'allow_duplicate' => true,
            ]);
        });
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'target_amount' => ['required', 'string', 'max:30'],
            'currency' => ['required', 'string', 'size:3'],
            'target_date' => ['nullable', 'date'],
            'account' => ['nullable', 'string'],
            'monthly_contribution' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $currency = strtoupper($data['currency']);

        return [
            'name' => $data['name'], 'currency' => $currency, 'target_amount' => $this->money($data['target_amount'], $currency),
            'target_date' => $data['target_date'] ?? null,
            'account_id' => filled($data['account'] ?? null) ? Account::query()->where('uuid', $data['account'])->value('id') : null,
            'monthly_contribution' => filled($data['monthly_contribution'] ?? null) ? $this->money($data['monthly_contribution'], $currency) : null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
