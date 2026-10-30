<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Savings\Http\Controllers\Concerns\UsesLedger;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\Commitment;
use Modules\Savings\Models\Expected;
use Modules\Savings\Models\Goal;
use Modules\Savings\Models\RecurringRule;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Services\Planning;

class PlanningController extends Controller
{
    use UsesLedger;

    public const KINDS = ['income', 'expense', 'transfer', 'contribution'];

    public function __construct(protected Planning $planning)
    {
    }

    public function index(): View
    {
        $expected = Expected::query()->where('status', 'expected')->with(['account', 'toAccount', 'category'])->orderBy('expected_date')->limit(200)->get();

        return view('savings::planning.index', [
            'expected' => $expected,
            'groups' => Expected::query()->whereNotNull('group_label')->get()->groupBy('group_label')
                ->map(fn ($g) => ['total' => (int) $g->whereIn('status', ['expected', 'posted'])->sum('amount'), 'received' => (int) $g->where('status', 'posted')->sum('amount')])
                ->filter(fn ($g) => $g['received'] < $g['total']),
            'done' => Expected::query()->whereIn('status', ['posted', 'skipped'])->with('transaction')->latest('expected_date')->limit(15)->get(),
            'rules' => RecurringRule::query()->with('account')->orderBy('description')->get(),
            'commitments' => Commitment::query()->with('account')->orderBy('status')->orderBy('due_date')->get(),
            'accounts' => Account::query()->where('status', 'active')->orderBy('name')->get(),
            'categories' => Category::query()->where('is_archived', false)->with('parent')->orderBy('name')->get(),
            'goals' => Goal::query()->where('status', 'active')->orderBy('name')->get(),
            'recent' => Transaction::query()->whereNull('reversed_at')->whereNull('reverses_id')->latest('effective_date')->limit(60)->get(),
        ]);
    }

    public function storeExpected(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(self::KINDS)],
            'description' => ['required', 'string', 'max:255'],
            'account' => ['required', 'string'],
            'to_account' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'goal' => ['nullable', 'string'],
            'confidence' => ['required', Rule::in(['confirmed', 'expected', 'uncertain'])],
            'parts' => ['required', 'array', 'min:1', 'max:24'],
            'parts.*.date' => ['required', 'date'],
            'parts.*.amount' => ['required', 'string', 'max:30'],
        ]);

        return $this->attempt(function () use ($data) {
            $account = Account::query()->where('uuid', $data['account'])->firstOrFail();
            $parts = array_values($data['parts']);
            foreach ($parts as $part) {
                $this->planning->expect([
                    'kind' => $data['kind'], 'description' => $data['description'], 'group_label' => count($parts) > 1 ? $data['description'] : null,
                    'account_id' => $account->id,
                    'to_account_id' => filled($data['to_account'] ?? null) ? Account::query()->where('uuid', $data['to_account'])->value('id') : null,
                    'category_id' => filled($data['category'] ?? null) ? Category::query()->where('uuid', $data['category'])->value('id') : null,
                    'goal_id' => filled($data['goal'] ?? null) ? Goal::query()->where('uuid', $data['goal'])->value('id') : null,
                    'amount' => $this->money($part['amount'], $account->currency), 'expected_date' => $part['date'], 'confidence' => $data['confidence'],
                ]);
            }
        }, __('savings::savings.expected_saved'));
    }

    public function updateExpected(Request $request, Expected $expected): RedirectResponse
    {
        $data = $request->validate(['expected_date' => ['required', 'date'], 'amount' => ['required', 'string', 'max:30'], 'confidence' => ['required', Rule::in(['confirmed', 'expected', 'uncertain'])]]);

        return $this->attempt(function () use ($data, $expected) {
            if (! $expected->isOpen()) {
                throw new \Modules\Savings\Exceptions\LedgerException(__('savings::savings.expected_closed'));
            }
            $expected->fill(['expected_date' => $data['expected_date'], 'amount' => $this->money($data['amount'], $expected->account->currency), 'confidence' => $data['confidence']])->save();
        }, __('savings::savings.expected_saved'));
    }

    public function post(Request $request, Expected $expected): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'string', 'max:30'], 'effective_date' => ['required', 'date']]);

        return $this->attempt(fn () => $this->planning->post($expected, $this->money($data['amount'], $expected->account->currency), $data['effective_date']));
    }

    public function match(Request $request, Expected $expected): RedirectResponse
    {
        $request->validate(['transaction' => ['required', 'string']]);

        return $this->attempt(fn () => $this->planning->match($expected, Transaction::query()->where('uuid', $request->input('transaction'))->firstOrFail()), __('savings::savings.expected_saved'));
    }

    public function skip(Request $request, Expected $expected): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return $this->attempt(fn () => $this->planning->skip($expected, $request->input('reason')), __('savings::savings.expected_saved'));
    }

    public function storeRule(Request $request): RedirectResponse
    {
        return $this->attempt(fn () => $this->planning->saveRule(new RecurringRule, $this->ruleData($request)), __('savings::savings.rule_saved'));
    }

    public function updateRule(Request $request, RecurringRule $rule): RedirectResponse
    {
        return $this->attempt(fn () => $this->planning->saveRule($rule, $this->ruleData($request) + ['is_active' => $request->boolean('is_active')]), __('savings::savings.rule_saved'));
    }

    public function storeCommitment(Request $request): RedirectResponse
    {
        $data = $request->validate(['account' => ['required', 'string'], 'description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'string'], 'due_date' => ['nullable', 'date']]);

        return $this->attempt(function () use ($data) {
            $account = Account::query()->where('uuid', $data['account'])->firstOrFail();

            return $this->planning->commit($account, $data['description'], $this->money($data['amount'], $account->currency), $data['due_date'] ?? null);
        }, __('savings::savings.committed_saved'));
    }

    public function settleCommitment(Request $request, Commitment $commitment): RedirectResponse
    {
        $request->validate(['category' => ['required', 'string']]);

        return $this->attempt(fn () => $this->planning->settleCommitment($commitment, Category::query()->where('uuid', $request->input('category'))->where('kind', 'expense')->firstOrFail()));
    }

    public function cancelCommitment(Commitment $commitment): RedirectResponse
    {
        $this->planning->cancelCommitment($commitment);

        return back()->with('success', __('savings::savings.committed_saved'));
    }

    private function ruleData(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(self::KINDS)],
            'description' => ['required', 'string', 'max:255'],
            'account' => ['required', 'string'],
            'to_account' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'goal' => ['nullable', 'string'],
            'amount' => ['required', 'string', 'max:30'],
            'frequency' => ['required', Rule::in(['day', 'week', 'month', 'year'])],
            'interval' => ['required', 'integer', 'min:1', 'max:52'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'confidence' => ['required', Rule::in(['confirmed', 'expected', 'uncertain'])],
        ]);
        $account = Account::query()->where('uuid', $data['account'])->firstOrFail();

        return [
            'kind' => $data['kind'], 'description' => $data['description'], 'account_id' => $account->id,
            'to_account_id' => filled($data['to_account'] ?? null) ? Account::query()->where('uuid', $data['to_account'])->value('id') : null,
            'category_id' => filled($data['category'] ?? null) ? Category::query()->where('uuid', $data['category'])->value('id') : null,
            'goal_id' => filled($data['goal'] ?? null) ? Goal::query()->where('uuid', $data['goal'])->value('id') : null,
            'amount' => $this->money($data['amount'], $account->currency), 'is_variable' => $request->boolean('is_variable'),
            'frequency' => $data['frequency'], 'interval' => (int) $data['interval'], 'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null, 'confidence' => $data['confidence'], 'is_active' => true,
        ];
    }
}
