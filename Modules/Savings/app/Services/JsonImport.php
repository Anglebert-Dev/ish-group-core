<?php

namespace Modules\Savings\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Savings\Exceptions\LedgerException;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\Commitment;
use Modules\Savings\Models\Expected;
use Modules\Savings\Models\Goal;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\RecurringRule;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Support\Money;
use Modules\Savings\Support\Settings;

/**
 * "Your finances in one file": a readable JSON (names instead of ids,
 * normal amounts) that fills everything through the same services as the
 * screens, so every ledger rule applies. All or nothing; uploading the same
 * file again adds nothing twice. Keys starting with "_" are notes and ignored.
 */
class JsonImport
{
    public const FORMAT = 'ish-finance';

    public const TRANSACTION_TYPES = ['income', 'expense', 'transfer', 'refund', 'split', 'lend', 'borrow', 'repayment_received', 'repayment_made', 'put_back', 'goal_contribution', 'adjustment'];

    private const DIRECTIONS = ['owed_to_me' => 'receivable', 'i_owe' => 'payable', 'to_put_back' => 'restore'];

    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, int> */
    private array $added = [];

    /** @var array<string, int> */
    private array $skipped = [];

    /** @var array<string, true> accounts in the file that could not be created (already reported) */
    private array $badAccounts = [];

    /** @var array<string, true> refs written in the file (a failed one is reported once, not again where it is used) */
    private array $fileRefs = [];

    /** @var array<string, Transaction> by ref */
    private array $refs = [];

    /** @var array<string, list<array{debt: string, count: mixed, path: string}>> "to_put_back" counts waiting for their transaction, by ref */
    private array $counts = [];

    public function __construct(
        protected Operations $operations,
        protected Obligations $obligations,
        protected Planning $planning,
        protected Budgets $budgets,
        protected Periods $periods,
    ) {
    }

    /**
     * @return array{added: array<string, int>, skipped: array<string, int>}
     *
     * @throws LedgerException with every problem found (nothing saved)
     */
    public function import(array $data): array
    {
        if (($data['format'] ?? null) !== self::FORMAT) {
            throw new LedgerException(__('savings::savings.json_not_ours'));
        }

        $level = DB::transactionLevel();
        DB::beginTransaction();

        try {
            $this->settings((array) ($data['settings'] ?? []));
            $this->categories((array) ($data['categories'] ?? []));
            $this->accounts($this->rows($data, 'accounts'));
            $this->debts($this->rows($data, 'debts'));
            $this->goals($this->rows($data, 'goals'));
            $this->transactions($this->rows($data, 'transactions'));
            foreach ($this->counts as $ref => $waiting) {
                if (isset($this->fileRefs[$ref])) {
                    continue;
                }
                $this->errors[] = $waiting[0]['path'].': '.__('savings::savings.json_unknown_ref', ['ref' => $ref]);
            }
            $this->budgetRows($this->rows($data, 'budgets'));
            $this->recurring($this->rows($data, 'recurring'));
            $this->expected($this->rows($data, 'expected'));
            $this->commitments($this->rows($data, 'commitments'));
        } catch (\Throwable $e) {
            $this->errors[] = $e instanceof LedgerException || $e instanceof \InvalidArgumentException ? $e->getMessage() : __('savings::savings.json_failed', ['error' => $e->getMessage()]);
        }

        if ($this->errors !== []) {
            DB::rollBack($level);

            throw new LedgerException(implode("\n", array_slice(array_unique($this->errors), 0, 50)));
        }

        DB::commit();
        audit('savings_json_import', __('savings::savings.audit_json_import', ['count' => array_sum($this->added)]), null, ['added' => $this->added, 'skipped' => $this->skipped]);

        return ['added' => $this->added, 'skipped' => $this->skipped];
    }

    // ---- sections -------------------------------------------------------------------

    private function settings(array $settings): void
    {
        if (isset($settings['currency'])) {
            Settings::set('currency', strtoupper((string) $settings['currency']));
        }
        if (isset($settings['period']) && is_array($settings['period'])) {
            $p = $settings['period'];
            if (! in_array($p['type'] ?? 'monthly', ['monthly', 'weekly', 'biweekly'], true)) {
                $this->errors[] = 'settings → period → type: monthly, weekly or biweekly';

                return;
            }
            Settings::set('period', array_merge(config('savings.period'), array_intersect_key($p, array_flip(['type', 'start_day', 'weekday', 'anchor']))));
        }
        foreach (['available', 'alerts'] as $group) {
            if (isset($settings[$group]) && is_array($settings[$group])) {
                $allowed = array_intersect_key($settings[$group], config("savings.{$group}"));
                Settings::set($group, array_merge(config("savings.{$group}"), (array) Settings::get($group, []), array_map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN), $allowed)));
            }
        }
    }

    private function categories(array $categories): void
    {
        foreach (['income', 'expense'] as $kind) {
            foreach ((array) ($categories[$kind] ?? []) as $i => $entry) {
                $name = is_array($entry) ? ($entry['name'] ?? null) : $entry;
                if (! is_string($name) || trim($name) === '') {
                    $this->errors[] = "categories → {$kind} item ".($i + 1).': ' .__('savings::savings.json_name_required');

                    continue;
                }
                $parent = $this->category($name, $kind, null, true);
                foreach ((array) (is_array($entry) ? ($entry['children'] ?? []) : []) as $child) {
                    $this->category((string) $child, $kind, $parent, true);
                }
            }
        }
    }

    private function accounts(array $rows): void
    {
        foreach ($rows as $i => $row) {
            $path = $this->where('accounts', $i, $row);
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $this->errors[] = "{$path} → name: ".__('savings::savings.json_name_required');

                continue;
            }
            if (Account::query()->where('name', $name)->exists()) {
                $this->skip('accounts');

                continue;
            }
            $type = $row['type'] ?? 'bank';
            if (! in_array($type, \Modules\Savings\Http\Controllers\AccountController::TYPES, true)) {
                $this->badAccounts[$name] = true;
                $this->errors[] = "{$path} → type: ".implode(', ', \Modules\Savings\Http\Controllers\AccountController::TYPES);

                continue;
            }
            $currency = strtoupper((string) ($row['currency'] ?? Money::baseCurrency()));
            $account = new Account([
                'name' => $name, 'type' => $type, 'currency' => $currency,
                'opening_date' => $this->date($row['opening_date'] ?? null, "{$path} → opening_date") ?? now()->toDateString(),
                'opening_balance' => $this->money($row['opening_balance'] ?? '0', $currency, "{$path} → opening_balance", true) ?? 0,
                'minimum_balance' => isset($row['minimum_balance']) ? $this->money($row['minimum_balance'], $currency, "{$path} → minimum_balance", true) : null,
                'institution' => $row['institution'] ?? null, 'reference' => $row['account_number'] ?? null,
                'is_liquid' => (bool) ($row['spendable'] ?? ! in_array($type, ['savings', 'investment', 'loan', 'credit_card'], true)),
                'status' => 'active', 'notes' => $row['notes'] ?? null,
            ]);
            $account->save();
            $this->add('accounts');
        }
    }

    private function debts(array $rows): void
    {
        foreach ($rows as $i => $row) {
            $path = $this->where('debts', $i, $row);
            $direction = self::DIRECTIONS[$row['direction'] ?? ''] ?? null;
            $name = trim((string) ($row['name'] ?? ''));
            if (! $direction || $name === '') {
                $this->errors[] = "{$path}: direction (owed_to_me, i_owe, to_put_back) and name are required";

                continue;
            }
            $account = isset($row['account']) ? $this->account($row['account'], "{$path} → account") : null;
            if ($direction === 'restore' && ! $account) {
                $this->errors[] = "{$path} → account: ".__('savings::savings.json_restore_account');

                continue;
            }
            if ($direction === 'restore') {
                foreach ((array) ($row['counts'] ?? []) as $j => $count) {
                    $ref = (string) (is_array($count) ? ($count['ref'] ?? '') : $count);
                    $this->counts[$ref][] = ['debt' => $name, 'count' => $count, 'path' => "{$path} → counts item ".($j + 1)];
                }
            }
            if ($this->debt($direction, $name, false)) {
                $this->skip('debts');

                continue;
            }
            $currency = $account?->currency ?? Money::baseCurrency();
            $this->obligations->create([
                'direction' => $direction, 'counterparty' => $direction === 'restore' ? null : $name,
                'description' => $direction === 'restore' ? $name : ($row['description'] ?? $name), 'currency' => $currency, 'account_id' => $account?->id,
                'due_date' => $this->date($row['due_date'] ?? null, "{$path} → due_date"), 'notes' => $row['notes'] ?? null,
            ], isset($row['amount']) ? $this->money($row['amount'], $currency, "{$path} → amount") : null, $row['note'] ?? null);
            $this->add('debts');
        }
    }

    private function transactions(array $rows): void
    {
        // Oldest first, so balances and refunds build up in order.
        uasort($rows, fn ($a, $b) => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));
        $this->fileRefs = array_fill_keys(array_filter(array_map(fn ($row) => is_string($row['ref'] ?? null) ? $row['ref'] : null, $rows)), true);

        foreach ($rows as $i => $row) {
            $path = $this->where('transactions', $i, $row);
            $type = $row['type'] ?? null;
            if (! in_array($type, self::TRANSACTION_TYPES, true)) {
                $this->errors[] = "{$path} → type: ".implode(', ', self::TRANSACTION_TYPES);

                continue;
            }
            $date = $this->date($row['date'] ?? null, "{$path} → date", true);
            $key = 'json:'.($row['ref'] ?? hash('sha256', json_encode($row)));
            if ($existing = Transaction::query()->where('idempotency_key', $key)->first()) {
                if (isset($row['ref'])) {
                    $this->posted($row['ref'], $existing);
                }
                $this->skip('transactions');

                continue;
            }
            if (! $date) {
                continue;
            }

            $meta = array_filter([
                'effective_date' => $date, 'description' => $row['description'] ?? null, 'counterparty' => $row['counterparty'] ?? null,
                'reference' => $row['reference'] ?? null, 'tags' => isset($row['tags']) ? array_values((array) $row['tags']) : null,
                'notes' => $row['notes'] ?? null, 'idempotency_key' => $key, 'allow_duplicate' => true, 'source' => 'import',
            ], fn ($v) => $v !== null);

            try {
                $tx = $this->transaction($type, $row, $path, $meta);
            } catch (LedgerException|\InvalidArgumentException $e) {
                $this->errors[] = "{$path}: ".$e->getMessage();

                continue;
            }

            if ($tx) {
                if (isset($row['ref'])) {
                    $this->posted($row['ref'], $tx);
                }
                $this->add('transactions');
            }
        }
    }

    private function transaction(string $type, array $row, string $path, array $meta): ?Transaction
    {
        $account = fn (string $field = 'account') => $this->account($row[$field] ?? null, "{$path} → {$field}");
        $amount = fn (Account $a, string $field = 'amount') => $this->money($row[$field] ?? null, $a->currency, "{$path} → {$field}");

        switch ($type) {
            case 'income':
            case 'expense':
                $a = $account();
                $category = $this->category($row['category'] ?? '', $type, null, false, "{$path} → category");
                if (! $a || ! $category || ($value = $amount($a)) === null) {
                    return null;
                }

                return $type === 'income' ? $this->operations->income($a, $value, $category, $meta) : $this->operations->expense($a, $value, $category, $meta);

            case 'transfer':
                $from = $account('from');
                $to = $account('to');
                if (! $from || ! $to || ($value = $amount($from)) === null) {
                    return null;
                }
                $fee = isset($row['fee']) ? $this->money($row['fee'], $from->currency, "{$path} → fee", true) : 0;
                $feeCategory = $fee ? $this->category($row['fee_category'] ?? '', 'expense', null, false, "{$path} → fee_category") : null;
                $received = isset($row['received']) ? $this->money($row['received'], $to->currency, "{$path} → received") : null;

                return $this->operations->transfer($from, $to, $value, $meta, $received, $row['rate'] ?? null, (int) $fee, $feeCategory);

            case 'refund':
                $original = $this->refs[$row['refund_of'] ?? ''] ?? null;
                $a = $account();
                if (! $original) {
                    if (isset($this->fileRefs[$row['refund_of'] ?? ''])) {
                        return null;
                    }
                    $this->errors[] = "{$path} → refund_of: ".__('savings::savings.json_unknown_ref', ['ref' => $row['refund_of'] ?? '']);

                    return null;
                }
                if (! $a || ($value = $amount($a)) === null) {
                    return null;
                }

                return $this->operations->refund($original, $a, $value, $meta);

            case 'split':
                $a = $account();
                $category = $this->category($row['category'] ?? '', 'expense', null, false, "{$path} → category");
                if (! $a || ! $category || ($value = $amount($a)) === null || ($back = $amount($a, 'owed_back')) === null) {
                    return null;
                }

                // amount = the whole payment (as on the statement); the rest after owed_back is the real expense.
                if ($back > $value) {
                    throw new LedgerException('owed_back: '.__('savings::savings.amount_invalid'));
                }

                return $this->operations->splitPayment($a, $value - $back, $category, $back, $this->debtFor('receivable', $row['owed_by'] ?? null, $a, $row, "{$path} → owed_by"), $meta);

            case 'lend':
            case 'borrow':
                $a = $account();
                if (! $a || ($value = $amount($a)) === null) {
                    return null;
                }
                $direction = $type === 'lend' ? 'receivable' : 'payable';
                $debt = $this->debtFor($direction, $row[$type === 'lend' ? 'to' : 'from'] ?? null, $a, $row, "{$path} → ".($type === 'lend' ? 'to' : 'from'));

                return $type === 'lend' ? $this->operations->lend($a, $value, $debt, $meta) : $this->operations->borrow($a, $value, $debt, $meta);

            case 'repayment_received':
            case 'repayment_made':
                $a = $account();
                $debt = $this->debt($type === 'repayment_received' ? 'receivable' : 'payable', (string) ($row['debt'] ?? ''), true, "{$path} → debt");
                if (! $a || ! $debt || ($value = $amount($a)) === null) {
                    return null;
                }
                $interest = isset($row['interest']) ? (int) $this->money($row['interest'], $a->currency, "{$path} → interest", true) : 0;
                $interestCategory = $interest ? $this->category($row['interest_category'] ?? '', 'expense', null, false, "{$path} → interest_category") : null;

                return $this->obligations->repay($debt, $a, $value, $meta, $interest, $interestCategory);

            case 'put_back':
                $a = $account();
                $debt = $this->debt('restore', (string) ($row['debt'] ?? ''), true, "{$path} → debt");
                if (! $a || ! $debt || ($value = $amount($a)) === null) {
                    return null;
                }

                return $this->obligations->repay($debt, $a, $value, $meta);

            case 'goal_contribution':
                $a = $account();
                $goal = $this->goal($row['goal'] ?? null, "{$path} → goal");
                if (! $a || ! $goal || ($value = $amount($a)) === null) {
                    return null;
                }

                return $this->planning->contribute($goal, $a, $value, $meta);

            case 'adjustment':
                $a = $account();
                if (! $a || ($value = $this->money($row['amount'] ?? null, $a->currency, "{$path} → amount")) === null) {
                    return null;
                }

                return $this->operations->adjust($a, $value, (string) ($row['reason'] ?? ''), $meta);
        }

        return null;
    }

    /**
     * Remember a transaction by its ref; if a "to_put_back" counts it, count it now (each once),
     * so later put_back payments in the same file find something to pay back.
     */
    private function posted(string $ref, Transaction $tx): void
    {
        $this->refs[$ref] = $tx;

        foreach ($this->counts[$ref] ?? [] as $waiting) {
            $debt = $this->debt('restore', $waiting['debt'], false);
            $count = $waiting['count'];
            if (! $debt || \Modules\Savings\Models\ObligationItem::query()->where('obligation_id', $debt->id)->where('type', 'basis')->where('transaction_id', $tx->id)->exists()) {
                continue;
            }
            $amount = is_array($count) && isset($count['amount']) ? $this->money($count['amount'], $debt->currency, "{$waiting['path']} → amount") : $tx->amount;
            try {
                if ($amount !== null) {
                    $this->obligations->addBasis($debt, $tx, $amount, is_array($count) ? ($count['note'] ?? null) : null);
                }
            } catch (LedgerException $e) {
                $this->errors[] = "{$waiting['path']}: ".$e->getMessage();
            }
        }
        unset($this->counts[$ref]);
    }

    private function budgetRows(array $rows): void
    {
        foreach ($rows as $i => $row) {
            $path = $this->where('budgets', $i, $row);
            $date = $this->date($row['period'] ?? null, "{$path} → period", true);
            $category = $this->category($row['category'] ?? '', 'expense', null, false, "{$path} → category");
            $limit = $this->money($row['limit'] ?? null, Money::baseCurrency(), "{$path} → limit");
            if ($date && $category && $limit !== null) {
                [$start, $end] = $this->periods->for($date);
                $this->budgets->set($category, $start, $end, $limit);
                $this->add('budgets');
            }
        }
    }

    private function goals(array $rows): void
    {
        foreach ($rows as $i => $row) {
            $path = $this->where('goals', $i, $row);
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $this->errors[] = "{$path} → name: ".__('savings::savings.json_name_required');

                continue;
            }
            if (Goal::query()->where('name', $name)->exists()) {
                $this->skip('goals');

                continue;
            }
            $account = isset($row['account']) ? $this->account($row['account'], "{$path} → account") : null;
            $currency = $account?->currency ?? strtoupper((string) ($row['currency'] ?? Money::baseCurrency()));
            $target = $this->money($row['target'] ?? null, $currency, "{$path} → target");
            if ($target === null) {
                continue;
            }
            $goal = new Goal([
                'name' => $name, 'target_amount' => $target, 'currency' => $currency, 'account_id' => $account?->id,
                'target_date' => $this->date($row['by'] ?? null, "{$path} → by"), 'status' => 'active',
                'monthly_contribution' => isset($row['monthly']) ? $this->money($row['monthly'], $currency, "{$path} → monthly") : null,
            ]);
            $goal->save();
            if (isset($row['earmarked'])) {
                $earmark = $this->money($row['earmarked'], $currency, "{$path} → earmarked");
                if ($earmark) {
                    $this->planning->allocate($goal, $earmark, __('savings::savings.json_imported'));
                }
            }
            $this->add('goals');
        }
    }

    private function recurring(array $rows): void
    {
        foreach ($rows as $i => $row) {
            $path = $this->where('recurring', $i, $row);
            $account = $this->account($row['account'] ?? null, "{$path} → account");
            $kind = $row['kind'] ?? 'expense';
            if (! in_array($kind, ['income', 'expense', 'transfer', 'contribution'], true)) {
                $this->errors[] = "{$path} → kind: income, expense, transfer or contribution";

                continue;
            }
            if (! $account) {
                continue;
            }
            $description = (string) ($row['description'] ?? '');
            if (RecurringRule::query()->where('description', $description)->where('account_id', $account->id)->exists()) {
                $this->skip('recurring');

                continue;
            }
            $amount = $this->money($row['amount'] ?? null, $account->currency, "{$path} → amount");
            $start = $this->date($row['start'] ?? null, "{$path} → start", true);
            $category = in_array($kind, ['income', 'expense'], true) ? $this->category($row['category'] ?? '', $kind, null, false, "{$path} → category") : null;
            if ($amount === null || ! $start || (in_array($kind, ['income', 'expense'], true) && ! $category)) {
                continue;
            }
            $this->planning->saveRule(new RecurringRule, [
                'kind' => $kind, 'description' => $description ?: ($category?->name ?? $kind), 'account_id' => $account->id,
                'to_account_id' => $kind === 'transfer' ? $this->account($row['to'] ?? null, "{$path} → to")?->id : null,
                'goal_id' => $kind === 'contribution' ? $this->goal($row['goal'] ?? null, "{$path} → goal")?->id : null,
                'category_id' => $category?->id, 'amount' => $amount, 'is_variable' => (bool) ($row['variable'] ?? false),
                'frequency' => in_array($row['unit'] ?? 'month', ['day', 'week', 'month', 'year'], true) ? ($row['unit'] ?? 'month') : 'month',
                'interval' => max(1, (int) ($row['every'] ?? 1)), 'start_date' => $start,
                'end_date' => $this->date($row['end'] ?? null, "{$path} → end"), 'confidence' => $row['confidence'] ?? 'confirmed', 'is_active' => true,
            ]);
            $this->add('recurring');
        }
    }

    private function expected(array $rows): void
    {
        foreach ($rows as $i => $row) {
            $path = $this->where('expected', $i, $row);
            $account = $this->account($row['account'] ?? null, "{$path} → account");
            $kind = $row['kind'] ?? 'income';
            if (! in_array($kind, ['income', 'expense', 'transfer', 'contribution'], true)) {
                $this->errors[] = "{$path} → kind: income, expense, transfer or contribution";

                continue;
            }
            $to = $kind === 'transfer' ? $this->account($row['to'] ?? null, "{$path} → to") : null;
            $goal = $kind === 'contribution' ? $this->goal($row['goal'] ?? null, "{$path} → goal") : null;
            if (($kind === 'transfer' && ! $to) || ($kind === 'contribution' && ! $goal)) {
                continue;
            }
            $category = in_array($kind, ['income', 'expense'], true) ? $this->category($row['category'] ?? '', $kind, null, false, "{$path} → category") : null;
            if (! $account || (in_array($kind, ['income', 'expense'], true) && ! $category)) {
                continue;
            }
            $parts = isset($row['parts']) ? (array) $row['parts'] : [['date' => $row['date'] ?? null, 'amount' => $row['amount'] ?? null]];
            foreach ($parts as $j => $part) {
                $date = $this->date($part['date'] ?? null, "{$path} → parts item ".($j + 1)." → date", true);
                $amount = $this->money($part['amount'] ?? null, $account->currency, "{$path} → parts item ".($j + 1)." → amount");
                if (! $date || $amount === null) {
                    continue;
                }
                $exists = Expected::query()->where('description', $row['description'] ?? '')->where('account_id', $account->id)->whereDate('expected_date', $date)->where('amount', $amount)->exists();
                if ($exists) {
                    $this->skip('expected');

                    continue;
                }
                $this->planning->expect([
                    'kind' => $kind, 'description' => (string) ($row['description'] ?? $category?->name ?? $kind),
                    'group_label' => count($parts) > 1 ? ($row['description'] ?? null) : null, 'account_id' => $account->id,
                    'to_account_id' => $to?->id, 'goal_id' => $goal?->id,
                    'category_id' => $category?->id, 'amount' => $amount, 'expected_date' => $date,
                    'confidence' => in_array($row['confidence'] ?? '', ['confirmed', 'expected', 'uncertain'], true) ? $row['confidence'] : 'expected',
                ]);
                $this->add('expected');
            }
        }
    }

    private function commitments(array $rows): void
    {
        foreach ($rows as $i => $row) {
            $path = $this->where('commitments', $i, $row);
            $account = $this->account($row['account'] ?? null, "{$path} → account");
            $amount = $account ? $this->money($row['amount'] ?? null, $account->currency, "{$path} → amount") : null;
            if (! $account || $amount === null) {
                continue;
            }
            if (Commitment::query()->where('description', $row['description'] ?? '')->where('account_id', $account->id)->where('status', 'open')->exists()) {
                $this->skip('commitments');

                continue;
            }
            $this->planning->commit($account, (string) ($row['description'] ?? ''), $amount, $this->date($row['due_date'] ?? null, "{$path} → due_date"));
            $this->add('commitments');
        }
    }

    // ---- helpers --------------------------------------------------------------------------

    private function rows(array $data, string $key): array
    {
        $rows = array_filter((array) ($data[$key] ?? []), fn ($row) => is_array($row));

        // Notes ("_" keys) are dropped; a row made only of notes (the guide at the top of a section) is skipped.
        $rows = array_map(fn ($row) => array_filter($row, fn ($k) => ! str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY), $rows);

        return array_filter($rows);
    }

    /**
     * Where a row is, as the person sees the file: "transactions item 3 (rent-feb)".
     */
    private function where(string $section, int|string $index, array $row): string
    {
        $label = $row['ref'] ?? $row['name'] ?? $row['description'] ?? null;

        return "{$section} item ".((int) $index + 1).(is_string($label) && $label !== '' ? " ({$label})" : '');
    }

    private function account(mixed $name, string $path): ?Account
    {
        $account = is_string($name) ? Account::query()->where('name', trim($name))->first() : null;
        if (! $account && ! isset($this->badAccounts[is_string($name) ? trim($name) : ''])) {
            $this->errors[] = "{$path}: ".__('savings::savings.json_unknown_account', ['name' => is_string($name) ? $name : '']);
        }

        return $account;
    }

    private function goal(mixed $name, string $path): ?Goal
    {
        $goal = is_string($name) ? Goal::query()->where('name', trim($name))->first() : null;
        if (! $goal) {
            $this->errors[] = "{$path}: ".__('savings::savings.json_unknown_goal', ['name' => is_string($name) ? $name : '']);
        }

        return $goal;
    }

    private function category(string $name, string $kind, ?Category $parent, bool $create, ?string $path = null): ?Category
    {
        $name = trim($name);
        $category = Category::query()->where('kind', $kind)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if (! $category && $create && $name !== '') {
            $category = new Category(['name' => $name, 'kind' => $kind, 'parent_id' => $parent?->id]);
            $category->save();
            $this->add('categories');
        } elseif (! $category && $path) {
            $this->errors[] = "{$path}: ".__('savings::savings.json_unknown_category', ['name' => $name, 'kind' => $kind]);
        }

        return $category;
    }

    private function debt(string $direction, string $name, bool $report, ?string $path = null): ?Obligation
    {
        $debt = Obligation::query()->where('direction', $direction)
            ->where(fn ($q) => $q->where('counterparty', $name)->orWhere(fn ($w) => $w->whereNull('counterparty')->where('description', $name)))
            ->where('status', '!=', 'written_off')->latest('id')->first();
        if (! $debt && $report) {
            $this->errors[] = "{$path}: ".__('savings::savings.json_unknown_debt', ['name' => $name]);
        }

        return $debt;
    }

    /**
     * The person's open debt in that direction, created on first use.
     */
    private function debtFor(string $direction, mixed $person, Account $account, array $row, string $path): Obligation
    {
        if (! is_string($person) || trim($person) === '') {
            throw new LedgerException("{$path}: ".__('savings::savings.json_name_required'));
        }

        return $this->debt($direction, trim($person), false) ?? $this->obligations->create([
            'direction' => $direction, 'counterparty' => trim($person), 'description' => $row['description'] ?? trim($person),
            'currency' => $account->currency, 'account_id' => $account->id, 'due_date' => $this->date($row['due_date'] ?? null, "{$path} → due_date"),
        ]);
    }

    private function money(mixed $value, string $currency, string $path, bool $allowZero = false): ?int
    {
        try {
            $minor = Money::parse(is_int($value) || is_string($value) ? (string) $value : '', $currency);
        } catch (\InvalidArgumentException $e) {
            $this->errors[] = "{$path}: ".$e->getMessage();

            return null;
        }

        if (! $allowZero && $minor === 0) {
            $this->errors[] = "{$path}: ".__('savings::savings.amount_invalid');

            return null;
        }

        return $minor;
    }

    private function date(mixed $value, string $path, bool $required = false): ?string
    {
        if ($value === null || $value === '') {
            if ($required) {
                $this->errors[] = "{$path}: ".__('savings::savings.json_date_required');
            }

            return null;
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || ! strtotime($value)) {
            $this->errors[] = "{$path}: ".__('savings::savings.json_date_format');

            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function add(string $section): void
    {
        $this->added[$section] = ($this->added[$section] ?? 0) + 1;
    }

    private function skip(string $section): void
    {
        $this->skipped[$section] = ($this->skipped[$section] ?? 0) + 1;
    }
}
