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
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Services\Ledger;
use Modules\Savings\Services\Obligations;
use Modules\Savings\Support\Money;

class ObligationController extends Controller
{
    use UsesLedger;

    public function __construct(protected Obligations $obligations)
    {
    }

    public function index(Request $request): View
    {
        $direction = in_array($request->query('direction'), ['receivable', 'payable', 'restore'], true) ? $request->query('direction') : null;
        $rows = Obligation::query()->when($direction, fn ($q) => $q->where('direction', $direction))->latest('id')->get()
            ->map(fn (Obligation $o) => [
                'o' => $o, 'original' => $this->obligations->original($o), 'outstanding' => $this->obligations->outstanding($o), 'state' => $this->obligations->state($o),
            ]);

        return view('savings::obligations.index', [
            'rows' => $rows,
            'direction' => $direction,
            'accounts' => Account::query()->where('status', 'active')->orderBy('name')->get(),
            'currency' => Money::baseCurrency(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['receivable', 'payable', 'restore'])],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'account' => [Rule::requiredIf($request->input('direction') === 'restore'), 'nullable', 'string'],
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['nullable', 'string', 'max:30'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->attempt(function () use ($data) {
            $currency = strtoupper($data['currency']);
            $account = filled($data['account'] ?? null) ? Account::query()->where('uuid', $data['account'])->firstOrFail() : null;

            return $this->obligations->create([
                'direction' => $data['direction'], 'counterparty' => $data['counterparty'] ?? null, 'description' => $data['description'],
                'currency' => $account?->currency ?? $currency, 'account_id' => $account?->id, 'due_date' => $data['due_date'] ?? null, 'notes' => $data['notes'] ?? null,
            ], filled($data['amount'] ?? null) ? $this->money($data['amount'], $account?->currency ?? $currency) : null);
        }, __('savings::savings.expected_saved'), fn ($o) => redirect()->route('savings.obligations.show', $o));
    }

    public function show(Obligation $obligation): View
    {
        $restoreAccount = $obligation->account_id;

        return view('savings::obligations.show', [
            'o' => $obligation,
            'original' => $this->obligations->original($obligation),
            'outstanding' => $this->obligations->outstanding($obligation),
            'state' => $this->obligations->state($obligation),
            'history' => $this->obligations->history($obligation),
            'accounts' => Account::query()->where('status', 'active')->orderBy('name')->get(),
            'expenseCategories' => Category::query()->where('kind', 'expense')->where('is_archived', false)->orderBy('name')->get(),
            // Candidates for "count a transaction" (money that left) and "already put back" (transfers into the account).
            'outgoing' => $obligation->direction === 'restore' ? Transaction::query()->whereNull('reversed_at')->whereNull('reverses_id')
                ->whereHas('entries', fn ($q) => $q->where('account_id', $restoreAccount)->where('amount', '<', 0))->latest('effective_date')->limit(50)->get() : collect(),
            'incoming' => $obligation->direction === 'restore' ? Transaction::query()->where('type', 'transfer')->whereNull('reversed_at')->whereNull('reverses_id')
                ->whereHas('entries', fn ($q) => $q->where('account_id', $restoreAccount)->where('amount', '>', 0))->latest('effective_date')->limit(50)->get() : collect(),
            'key' => Ledger::key(),
        ]);
    }

    public function repay(Request $request, Obligation $obligation): RedirectResponse
    {
        $data = $request->validate(['account' => ['required', 'string'], 'amount' => ['required', 'string'], 'interest' => ['nullable', 'string'], 'interest_category' => ['nullable', 'string'],
            'effective_date' => ['required', 'date'], 'idempotency_key' => ['required', 'string']]);

        return $this->attempt(function () use ($data, $obligation) {
            $account = Account::query()->where('uuid', $data['account'])->firstOrFail();
            $interest = filled($data['interest'] ?? null) ? $this->money($data['interest'], $account->currency, true) : 0;

            return $this->obligations->repay($obligation, $account, $this->money($data['amount'], $account->currency), [
                'effective_date' => $data['effective_date'], 'idempotency_key' => $data['idempotency_key'], 'allow_duplicate' => true,
                'description' => __('savings::savings.record_repayment').': '.($obligation->counterparty ?: $obligation->description),
                'counterparty' => $obligation->counterparty,
            ], $interest, $interest > 0 ? Category::query()->where('uuid', $data['interest_category'] ?? '')->first() : null);
        });
    }

    public function basis(Request $request, Obligation $obligation): RedirectResponse
    {
        $data = $request->validate(['transaction' => ['required', 'string'], 'amount' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->obligations->addBasis($obligation, Transaction::query()->where('uuid', $data['transaction'])->firstOrFail(),
            $this->money($data['amount'], $obligation->currency), $data['note'] ?? null), __('savings::savings.expected_saved'));
    }

    public function link(Request $request, Obligation $obligation): RedirectResponse
    {
        $data = $request->validate(['transaction' => ['required', 'string'], 'amount' => ['required', 'string']]);

        return $this->attempt(fn () => $this->obligations->linkRestoration($obligation, Transaction::query()->where('uuid', $data['transaction'])->firstOrFail(),
            $this->money($data['amount'], $obligation->currency)), __('savings::savings.expected_saved'));
    }

    public function adjust(Request $request, Obligation $obligation): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'string'], 'reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->obligations->adjust($obligation, $this->money($data['amount'], $obligation->currency), $data['reason']), __('savings::savings.expected_saved'));
    }

    public function dispute(Request $request, Obligation $obligation): RedirectResponse
    {
        return $this->attempt(fn () => $this->obligations->setDisputed($obligation, $obligation->status !== 'disputed', $request->input('reason')), __('savings::savings.expected_saved'));
    }

    public function writeOff(Request $request, Obligation $obligation): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->obligations->writeOff($obligation, $request->input('reason')), __('savings::savings.expected_saved'));
    }
}
