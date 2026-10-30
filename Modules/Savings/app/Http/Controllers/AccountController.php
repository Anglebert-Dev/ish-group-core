<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Savings\Http\Controllers\Concerns\UsesLedger;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Entry;
use Modules\Savings\Models\Reconciliation;
use Modules\Savings\Services\Balances;
use Modules\Savings\Services\Planning;
use Modules\Savings\Support\Money;

class AccountController extends Controller
{
    use UsesLedger;

    public const TYPES = ['bank', 'cash', 'mobile_money', 'savings', 'investment', 'credit_card', 'loan', 'other'];

    public function index(Balances $balances, Planning $planning): View
    {
        $accounts = Account::query()->orderBy('status')->orderByDesc('is_liquid')->orderBy('name')->get();

        return view('savings::accounts.index', [
            'accounts' => $accounts,
            'balances' => $balances->many($accounts),
            'available' => $accounts->where('status', 'active')->mapWithKeys(fn ($a) => [$a->id => $planning->available($a)['available']]),
            'currency' => Money::baseCurrency(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->attempt(function () use ($request) {
            $account = new Account($this->validated($request) + ['status' => 'active']);
            $account->save();

            return $account;
        }, __('savings::savings.account_saved'), fn ($account) => redirect()->route('savings.accounts.show', $account));
    }

    public function show(Account $account, Balances $balances, Planning $planning): View
    {
        $entries = Entry::query()->where('account_id', $account->id)->with('transaction')
            ->orderByDesc('effective_date')->orderByDesc('id')->paginate(30);

        // Running balance for the page, working back from the balance after the newest shown entry.
        $first = $entries->first();
        $running = $first ? $balances->balance($account) - (int) Entry::query()->where('account_id', $account->id)
            ->where(fn ($q) => $q->whereDate('effective_date', '>', $first->effective_date->toDateString())
                ->orWhere(fn ($w) => $w->whereDate('effective_date', $first->effective_date->toDateString())->where('id', '>', $first->id)))
            ->sum('amount') : null;
        $after = [];
        foreach ($entries as $entry) {
            $after[$entry->id] = $running;
            $running -= $entry->amount;
        }

        return view('savings::accounts.show', [
            'account' => $account,
            'balance' => $balances->balance($account),
            'available' => $planning->available($account),
            'entries' => $entries,
            'after' => $after,
            'reconciliations' => Reconciliation::query()->where('account_id', $account->id)->latest('statement_date')->limit(5)->get(),
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        return $this->attempt(function () use ($request, $account) {
            $data = $this->validated($request, $account);
            // Opening balance and currency shape the whole history: fixed once anything is posted.
            if ($account->entries()->exists()) {
                unset($data['currency'], $data['opening_balance'], $data['opening_date']);
            }
            $account->fill($data)->save();
        }, __('savings::savings.account_saved'));
    }

    public function archive(Account $account): RedirectResponse
    {
        $account->forceFill(['status' => $account->isArchived() ? 'active' : 'archived'])->save();

        return back()->with('success', __('savings::savings.account_saved'));
    }

    private function validated(Request $request, ?Account $account = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(self::TYPES)],
            'currency' => ['required', 'string', 'size:3'],
            'opening_date' => ['required', 'date'],
            'opening_balance' => ['nullable', 'string', 'max:30'],
            'minimum_balance' => ['nullable', 'string', 'max:30'],
            'institution' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $currency = strtoupper($data['currency']);

        return [
            'name' => $data['name'], 'type' => $data['type'], 'currency' => $currency, 'opening_date' => $data['opening_date'],
            'opening_balance' => filled($data['opening_balance'] ?? null) ? Money::parse($data['opening_balance'], $currency) : 0,
            'minimum_balance' => filled($data['minimum_balance'] ?? null) ? Money::parse($data['minimum_balance'], $currency) : null,
            'institution' => $data['institution'] ?? null, 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
            'is_liquid' => $request->boolean('is_liquid'),
        ];
    }
}
