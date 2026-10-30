<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Libraries\ListExport;
use App\Libraries\ListExportLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Savings\Http\Controllers\Concerns\UsesLedger;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Services\Ledger;
use Modules\Savings\Services\Obligations;
use Modules\Savings\Services\Operations;
use Modules\Savings\Support\Money;
use Symfony\Component\HttpFoundation\Response;

class TransactionController extends Controller
{
    use UsesLedger;

    public const FORM_TYPES = ['expense', 'income', 'transfer', 'split', 'advance', 'borrowing'];

    private const FILTERS = ['q', 'type', 'account', 'category', 'from', 'to', 'min', 'max', 'status'];

    public function __construct(protected Operations $operations, protected Ledger $ledger)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(self::FILTERS);

        return view('savings::transactions.index', [
            'transactions' => $this->filtered($filters)->with(['entries.account', 'lines.category'])->latest('effective_date')->latest('id')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'accounts' => Account::query()->orderBy('name')->get(),
            'categories' => Category::query()->with('parent')->orderBy('kind')->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $rows = $this->filtered($request->only(self::FILTERS))->with(['entries.account', 'lines.category'])->latest('effective_date')->limit(10000)->get()
            ->map(fn (Transaction $t) => [
                $t->effective_date->format('Y-m-d'), __('savings::savings.type_'.$t->type), $t->description, $t->counterparty ?: '',
                $t->entries->map(fn ($e) => $e->account?->name.' '.Money::format($e->amount, $e->account?->currency))->implode('; '),
                $t->lines->map(fn ($l) => __('savings::savings.nature_'.$l->nature).($l->category ? ' ('.$l->category->name.')' : ''))->implode('; '),
                $t->reference ?: '', $t->isReversed() ? __('savings::savings.reversed') : ($t->isReversal() ? __('savings::savings.reversal') : ''),
            ])->all();

        $export = ListExport::make(__('savings::savings.transactions'), 'transactions', [
            __('savings::savings.date'), __('savings::savings.type'), __('savings::savings.description'), __('savings::savings.counterparty'),
            __('savings::savings.entries'), __('savings::savings.lines'), __('savings::savings.reference'), __('body.status'),
        ], $rows);
        audit(\App\Enums\ActivityLogAction::Exported, __('savings::savings.transactions').' ('.$format.')');

        return $format === 'pdf' ? ListExportLibrary::pdf($export) : ListExportLibrary::excel($export);
    }

    public function create(Request $request): View
    {
        $replaces = $request->filled('replaces') ? Transaction::query()->where('uuid', $request->query('replaces'))->with(['entries.account', 'lines.category'])->first() : null;

        return view('savings::transactions.create', [
            'type' => in_array($request->query('type'), self::FORM_TYPES, true) ? $request->query('type') : ($replaces && in_array($replaces->type, self::FORM_TYPES, true) ? $replaces->type : 'expense'),
            'accounts' => Account::query()->where('status', 'active')->orderByDesc('is_liquid')->orderBy('name')->get(),
            'categories' => Category::query()->where('is_archived', false)->with('parent')->orderBy('name')->get(),
            'key' => old('idempotency_key', Ledger::key()),
            'replaces' => $replaces,
        ]);
    }

    public function store(Request $request, Obligations $obligations): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::FORM_TYPES)],
            'idempotency_key' => ['required', 'string', 'max:80'],
            'account' => ['required', 'string'],
            'to_account' => ['nullable', 'string'],
            'amount' => ['required', 'string', 'max:30'],
            'received' => ['nullable', 'string', 'max:30'],
            'fee' => ['nullable', 'string', 'max:30'],
            'fee_category' => ['nullable', 'string'],
            'owed_back' => ['nullable', 'string', 'max:30'],
            'category' => ['nullable', 'string'],
            'effective_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'original_amount' => ['nullable', 'string', 'max:30'],
            'original_currency' => ['nullable', 'string', 'size:3'],
            'replaces' => ['nullable', 'string'],
        ]);

        return $this->attempt(function () use ($data, $request, $obligations) {
            $account = Account::query()->where('uuid', $data['account'])->firstOrFail();
            $category = filled($data['category'] ?? null) ? Category::query()->where('uuid', $data['category'])->first() : null;
            $replaces = filled($data['replaces'] ?? null) ? Transaction::query()->where('uuid', $data['replaces'])->first() : null;
            $amount = $this->money($data['amount'], $account->currency);
            if ($amount < 0) {
                throw new \InvalidArgumentException(__('savings::savings.amount_invalid'));
            }
            $meta = [
                'effective_date' => $data['effective_date'],
                'description' => $data['description'] ?? null,
                'counterparty' => $data['counterparty'] ?? null,
                'reference' => $data['reference'] ?? null,
                'tags' => filled($data['tags'] ?? null) ? array_values(array_filter(array_map('trim', explode(',', $data['tags'])))) : null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'allow_duplicate' => $request->boolean('allow_duplicate') || $replaces !== null,
                'replaces' => $replaces,
            ];
            if (filled($data['original_amount'] ?? null) && filled($data['original_currency'] ?? null)) {
                $originalCurrency = strtoupper($data['original_currency']);
                $original = Money::parse($data['original_amount'], $originalCurrency);
                $meta += [
                    'original_amount' => $original,
                    'original_currency' => $originalCurrency,
                    'exchange_rate' => bcdiv(bcmul((string) $amount, bcpow('10', (string) Money::decimals($originalCurrency)), 12), bcmul((string) $original, bcpow('10', (string) Money::decimals($account->currency)), 12), 10),
                ];
            }

            return DB::transaction(function () use ($data, $account, $category, $amount, $meta, $obligations) {
                return match ($data['type']) {
                    'income' => $this->operations->income($account, $amount, $this->needs($category), $meta),
                    'expense' => $this->operations->expense($account, $amount, $this->needs($category), $meta),
                    'transfer' => $this->transfer($data, $account, $amount, $meta),
                    'split' => $this->operations->splitPayment($account, $amount, $this->needs($category), $this->money($data['owed_back'] ?? '', $account->currency),
                        $this->newObligation($obligations, 'receivable', $data, $account), $meta),
                    'advance' => $this->operations->lend($account, $amount, $this->newObligation($obligations, 'receivable', $data, $account), $meta),
                    'borrowing' => $this->operations->borrow($account, $amount, $this->newObligation($obligations, 'payable', $data, $account), $meta),
                };
            });
        }, $request->filled('replaces') ? __('savings::savings.corrected') : __('savings::savings.posted'), fn (Transaction $tx) => redirect()->route('savings.transactions.show', $tx));
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['entries.account', 'lines.category', 'reverses', 'reversal', 'refundOf', 'user']);

        return view('savings::transactions.show', [
            'tx' => $transaction,
            'replacedBy' => Transaction::query()->where('replaces_id', $transaction->id)->first(),
            'replaces' => $transaction->replaces_id ? Transaction::query()->find($transaction->replaces_id) : null,
            'refunds' => Transaction::query()->where('refund_of_id', $transaction->id)->get(),
            'obligations' => \Modules\Savings\Models\Obligation::query()->whereIn('id', $transaction->lines->pluck('obligation_id')->filter())->get(),
            'accounts' => Account::query()->where('status', 'active')->orderBy('name')->get(),
            'key' => Ledger::key(),
        ]);
    }

    public function reverse(Request $request, Transaction $transaction): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(
            fn () => $this->ledger->reverse($transaction, $request->input('reason')),
            null,
            fn () => $request->boolean('correct')
                ? redirect()->route('savings.transactions.create', ['replaces' => $transaction->uuid])
                : redirect()->route('savings.transactions.show', $transaction),
        );
    }

    public function refund(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate(['account' => ['required', 'string'], 'amount' => ['required', 'string'], 'effective_date' => ['required', 'date'], 'idempotency_key' => ['required', 'string']]);

        return $this->attempt(function () use ($data, $transaction) {
            $account = Account::query()->where('uuid', $data['account'])->firstOrFail();

            return $this->operations->refund($transaction, $account, $this->money($data['amount'], $account->currency), [
                'effective_date' => $data['effective_date'], 'idempotency_key' => $data['idempotency_key'],
                'description' => __('savings::savings.refund_of').' '.$transaction->description, 'allow_duplicate' => true,
            ]);
        }, null, fn (Transaction $tx) => redirect()->route('savings.transactions.show', $tx));
    }

    private function transfer(array $data, Account $from, int $amount, array $meta): Transaction
    {
        $to = Account::query()->where('uuid', $data['to_account'] ?? '')->firstOrFail();
        $fee = filled($data['fee'] ?? null) ? $this->money($data['fee'], $from->currency, true) : 0;
        $feeCategory = $fee > 0 ? $this->needs(Category::query()->where('uuid', $data['fee_category'] ?? '')->first()) : null;
        $received = $from->currency !== $to->currency && filled($data['received'] ?? null) ? $this->money($data['received'], $to->currency) : null;

        return $this->operations->transfer($from, $to, $amount, $meta, $received, null, $fee, $feeCategory);
    }

    private function newObligation(Obligations $obligations, string $direction, array $data, Account $account)
    {
        return $obligations->create([
            'direction' => $direction,
            'counterparty' => $data['counterparty'] ?? null,
            'description' => ($data['description'] ?? null) ?: (($data['counterparty'] ?? null) ?: __('savings::savings.direction_'.$direction)),
            'currency' => $account->currency,
            'account_id' => $account->id,
            'due_date' => $data['due_date'] ?? null,
        ]);
    }

    private function needs(?Category $category): Category
    {
        return $category ?? throw new \Modules\Savings\Exceptions\LedgerException(__('savings::savings.category_required', ['kind' => '']));
    }

    private function filtered(array $filters): Builder
    {
        return Transaction::query()
            ->when(trim((string) ($filters['q'] ?? '')), fn ($q, $s) => $q->where(fn ($w) => $w
                ->whereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower($s).'%'])
                ->orWhereRaw('LOWER(counterparty) LIKE ?', ['%'.mb_strtolower($s).'%'])
                ->orWhereRaw('LOWER(reference) LIKE ?', ['%'.mb_strtolower($s).'%'])))
            ->when(in_array($filters['type'] ?? null, Ledger::TYPES, true), fn ($q) => $q->where('type', $filters['type']))
            ->when($filters['account'] ?? null, fn ($q, $uuid) => $q->whereHas('entries.account', fn ($a) => $a->where('uuid', $uuid)))
            ->when($filters['category'] ?? null, fn ($q, $uuid) => $q->whereHas('lines.category', fn ($c) => $c->where('uuid', $uuid)))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('effective_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('effective_date', '<=', $d))
            ->when(is_numeric($filters['min'] ?? null), fn ($q) => $q->where('amount', '>=', Money::parse((string) $filters['min'], Money::baseCurrency())))
            ->when(is_numeric($filters['max'] ?? null), fn ($q) => $q->where('amount', '<=', Money::parse((string) $filters['max'], Money::baseCurrency())))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->whereNull('reversed_at')->whereNull('reverses_id'))
            ->when(($filters['status'] ?? null) === 'reversed', fn ($q) => $q->where(fn ($w) => $w->whereNotNull('reversed_at')->orWhereNotNull('reverses_id')));
    }
}
