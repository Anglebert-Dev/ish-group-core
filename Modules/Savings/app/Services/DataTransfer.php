<?php

namespace Modules\Savings\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Savings\Exceptions\LedgerException;
use Modules\Savings\Exceptions\LikelyDuplicate;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\ImportBatch;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Support\Money;

/**
 * Backup (JSON), restore (merge or replace) and CSV statement import.
 * Everything is keyed by UUID, so relationships survive and nothing is duplicated.
 */
class DataTransfer
{
    public const VERSION = 1;

    /** Tables in dependency order, with the columns that point to other rows. */
    private const TABLES = [
        'sav_accounts' => [],
        'sav_categories' => ['parent_id' => 'sav_categories'],
        'sav_obligations' => ['account_id' => 'sav_accounts'],
        'sav_goals' => ['account_id' => 'sav_accounts'],
        'sav_transactions' => ['reverses_id' => 'sav_transactions', 'replaces_id' => 'sav_transactions', 'refund_of_id' => 'sav_transactions'],
        'sav_entries' => ['transaction_id' => 'sav_transactions', 'account_id' => 'sav_accounts'],
        'sav_lines' => ['transaction_id' => 'sav_transactions', 'category_id' => 'sav_categories', 'obligation_id' => 'sav_obligations'],
        'sav_obligation_items' => ['obligation_id' => 'sav_obligations', 'transaction_id' => 'sav_transactions'],
        'sav_budgets' => ['category_id' => 'sav_categories'],
        'sav_goal_allocations' => ['goal_id' => 'sav_goals', 'transaction_id' => 'sav_transactions', 'related_goal_id' => 'sav_goals'],
        'sav_recurring_rules' => ['account_id' => 'sav_accounts', 'to_account_id' => 'sav_accounts', 'category_id' => 'sav_categories', 'goal_id' => 'sav_goals'],
        'sav_expected' => ['account_id' => 'sav_accounts', 'to_account_id' => 'sav_accounts', 'category_id' => 'sav_categories', 'obligation_id' => 'sav_obligations', 'goal_id' => 'sav_goals', 'recurring_rule_id' => 'sav_recurring_rules', 'transaction_id' => 'sav_transactions'],
        'sav_commitments' => ['account_id' => 'sav_accounts', 'transaction_id' => 'sav_transactions'],
        'sav_reconciliations' => ['account_id' => 'sav_accounts', 'adjustment_transaction_id' => 'sav_transactions'],
    ];

    /** Child tables without their own UUID: identified through their parent. */
    private const CHILDREN = ['sav_entries', 'sav_lines', 'sav_obligation_items', 'sav_goal_allocations'];

    public function __construct(protected Operations $operations, protected Ledger $ledger)
    {
    }

    // ---- Backup ------------------------------------------------------------------

    public function export(int $businessId, int $ownerId): array
    {
        $uuidOf = [];
        $data = ['format' => 'ish-savings', 'version' => self::VERSION, 'exported_at' => now()->toIso8601String(), 'settings' => [], 'tables' => []];

        foreach (array_keys(self::TABLES) as $table) {
            $hasUuid = ! in_array($table, self::CHILDREN, true);
            $rows = DB::table($table)->where('business_id', $businessId)->where('owner_id', $ownerId)->orderBy('id')->get();
            if ($hasUuid) {
                $uuidOf[$table] = $rows->pluck('uuid', 'id')->all();
            }
        }

        foreach (self::TABLES as $table => $refs) {
            $data['tables'][$table] = DB::table($table)->where('business_id', $businessId)->where('owner_id', $ownerId)->orderBy('id')->get()
                ->map(function ($row) use ($refs, $uuidOf) {
                    $row = (array) $row;
                    foreach ($refs as $column => $target) {
                        $row[$column] = $row[$column] !== null ? ($uuidOf[$target][$row[$column]] ?? null) : null;
                    }
                    unset($row['id'], $row['business_id'], $row['owner_id'], $row['insert_by'], $row['update_by'], $row['delete_by'], $row['user_id']);

                    return $row;
                })->all();
        }

        $prefix = "savings.user.{$ownerId}.";
        $data['settings'] = DB::table('business_settings')->where('business_id', $businessId)->where('key', 'like', $prefix.'%')->pluck('value', 'key')
            ->mapWithKeys(fn ($value, $key) => [substr($key, strlen($prefix)) => $value])->all();

        audit(\App\Enums\ActivityLogAction::Exported, __('savings::savings.audit_backup'), null, [], $businessId);

        return $data;
    }

    /**
     * Check a backup before touching anything. Returns a list of problems (empty = valid).
     *
     * @return list<string>
     */
    public function validate(array $data): array
    {
        $errors = [];

        if (($data['format'] ?? null) !== 'ish-savings' || ! is_array($data['tables'] ?? null)) {
            return [__('savings::savings.backup_not_ours')];
        }

        $uuids = [];
        foreach (self::TABLES as $table => $refs) {
            foreach ((array) ($data['tables'][$table] ?? []) as $row) {
                if (! in_array($table, self::CHILDREN, true)) {
                    $uuids[$table][$row['uuid'] ?? ''] = true;
                }
            }
        }

        foreach (self::TABLES as $table => $refs) {
            foreach ((array) ($data['tables'][$table] ?? []) as $i => $row) {
                foreach ($refs as $column => $target) {
                    if (($row[$column] ?? null) !== null && ! isset($uuids[$target][$row[$column]])) {
                        $errors[] = __('savings::savings.backup_broken_link', ['table' => $table, 'row' => $i + 1, 'column' => $column]);
                    }
                }
            }
        }

        // Every transaction must still balance or be a mirror/transfer the ledger accepted.
        $entries = collect($data['tables']['sav_entries'] ?? [])->groupBy('transaction_id');
        $lines = collect($data['tables']['sav_lines'] ?? [])->groupBy('transaction_id');
        foreach ((array) ($data['tables']['sav_transactions'] ?? []) as $tx) {
            $sum = (int) collect($entries[$tx['uuid']] ?? [])->sum('amount');
            $inOut = (int) collect($lines[$tx['uuid']] ?? [])->sum(fn ($l) => in_array($l['nature'], Ledger::IN, true) ? $l['amount'] : -$l['amount']);
            if ($sum !== $inOut && blank($tx['exchange_rate'] ?? null)) {
                $errors[] = __('savings::savings.backup_unbalanced', ['description' => $tx['description'] ?? '?']);
            }
        }

        return array_slice($errors, 0, 50);
    }

    /**
     * merge: add what isn't there yet (by UUID); replace: wipe this business's finance data first.
     *
     * @return array{added: array<string, int>, skipped: array<string, int>}
     */
    public function restore(int $businessId, int $ownerId, array $data, string $mode): array
    {
        if ($errors = $this->validate($data)) {
            throw new LedgerException(implode(' ', array_slice($errors, 0, 3)));
        }

        // Records in the file that belong to someone else are never touched.
        foreach (self::TABLES as $table => $refs) {
            if (in_array($table, self::CHILDREN, true)) {
                continue;
            }
            $uuids = array_column((array) ($data['tables'][$table] ?? []), 'uuid');
            if ($uuids && DB::table($table)->whereIn('uuid', $uuids)->where(fn ($q) => $q->where('business_id', '!=', $businessId)->orWhere('owner_id', '!=', $ownerId))->exists()) {
                throw new LedgerException(__('savings::savings.backup_someone_else'));
            }
        }

        return DB::transaction(function () use ($businessId, $ownerId, $data, $mode) {
            if ($mode === 'replace') {
                foreach (array_reverse(array_keys(self::TABLES)) as $table) {
                    DB::table($table)->where('business_id', $businessId)->where('owner_id', $ownerId)->delete();
                }
            }

            $idOf = [];
            $added = [];
            $skipped = [];
            $skippedParents = [];

            foreach (self::TABLES as $table => $refs) {
                $isChild = in_array($table, self::CHILDREN, true);
                $existing = $isChild ? [] : DB::table($table)->where('business_id', $businessId)->where('owner_id', $ownerId)->pluck('id', 'uuid')->all();
                $idOf[$table] = $existing;

                foreach ((array) ($data['tables'][$table] ?? []) as $row) {
                    $parentUuid = $isChild ? $row[array_key_first($refs)] : null;

                    // Children of a parent that was already here (merge) are already here too.
                    if ($isChild && isset($skippedParents[$refs[array_key_first($refs)]][$parentUuid])) {
                        $skipped[$table] = ($skipped[$table] ?? 0) + 1;

                        continue;
                    }
                    if (! $isChild && isset($existing[$row['uuid']])) {
                        $skippedParents[$table][$row['uuid']] = true;
                        $skipped[$table] = ($skipped[$table] ?? 0) + 1;

                        continue;
                    }

                    foreach ($refs as $column => $target) {
                        $row[$column] = $row[$column] !== null ? ($idOf[$target][$row[$column]] ?? null) : null;
                    }
                    $row['business_id'] = $businessId;
                    $row['owner_id'] = $ownerId;
                    if (array_key_exists('tags', $row) && is_array($row['tags'])) {
                        $row['tags'] = json_encode($row['tags']);
                    }
                    foreach (['values', 'rows'] as $json) {
                        if (isset($row[$json]) && is_array($row[$json])) {
                            $row[$json] = json_encode($row[$json]);
                        }
                    }

                    $id = DB::table($table)->insertGetId($row);
                    if (! $isChild) {
                        $idOf[$table][$row['uuid']] = $id;
                    }
                    $added[$table] = ($added[$table] ?? 0) + 1;
                }
            }

            foreach ((array) ($data['settings'] ?? []) as $key => $value) {
                $key = "savings.user.{$ownerId}.".preg_replace('/^savings\./', '', (string) $key);
                if ($mode === 'replace' || ! DB::table('business_settings')->where('business_id', $businessId)->where('key', $key)->exists()) {
                    DB::table('business_settings')->updateOrInsert(['business_id' => $businessId, 'key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
                }
            }

            audit('savings_restore', __('savings::savings.audit_restore', ['mode' => $mode, 'count' => array_sum($added)]), null, ['added' => $added, 'skipped' => $skipped], $businessId);

            return ['added' => $added, 'skipped' => $skipped];
        });
    }

    /**
     * Erase one person's finances in this business (everything, including their settings).
     * Ledger rows are never edited one by one; this removes the whole book at once.
     *
     * @return int rows removed
     */
    public function wipe(int $businessId, int $ownerId): int
    {
        $removed = DB::transaction(function () use ($businessId, $ownerId) {
            $count = 0;
            DB::table('sav_alerts_sent')->where('business_id', $businessId)->where('owner_id', $ownerId)->delete();
            DB::table('sav_import_batches')->where('business_id', $businessId)->where('owner_id', $ownerId)->delete();
            foreach (array_reverse(array_keys(self::TABLES)) as $table) {
                $count += DB::table($table)->where('business_id', $businessId)->where('owner_id', $ownerId)->delete();
            }
            DB::table('business_settings')->where('business_id', $businessId)->where('key', 'like', "savings.user.{$ownerId}.%")->delete();

            return $count;
        });

        audit('savings_reset', __('savings::savings.audit_reset', ['count' => $removed]), null, [], $businessId);

        return $removed;
    }

    // ---- CSV statement import ----------------------------------------------------------

    /**
     * Read a statement (columns: date, description, amount [+ in / − out], reference) into a preview.
     */
    public function preview(Account $account, string $path, string $filename): ImportBatch
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => Str::slug(trim((string) $h), '_'), fgetcsv($handle) ?: []);
        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($handle)) !== false && $line < 5001) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $raw = array_combine(array_slice($header, 0, count($cells)), array_slice($cells, 0, count($header))) ?: [];
            $rows[] = $this->checkRow($account, $raw, $line);
        }
        fclose($handle);

        $batch = new ImportBatch([
            'account_id' => $account->id, 'filename' => mb_substr($filename, 0, 255), 'status' => 'preview', 'rows' => $rows,
            'duplicates' => collect($rows)->where('status', 'duplicate')->count(),
            'rejected' => collect($rows)->where('status', 'invalid')->count(), 'user_id' => auth()->id(),
        ]);
        $batch->business_id = $account->business_id;
        $batch->save();

        return $batch;
    }

    /**
     * Post the rows marked ok (and duplicates the user explicitly ticked).
     */
    public function commit(ImportBatch $batch, Category $incomeCategory, Category $expenseCategory, array $alsoImport = []): ImportBatch
    {
        if ($batch->status !== 'preview') {
            throw new LedgerException(__('savings::savings.import_done'));
        }

        $account = Account::query()->findOrFail($batch->account_id);
        $rows = $batch->rows;
        $imported = 0;

        DB::transaction(function () use (&$rows, &$imported, $account, $batch, $incomeCategory, $expenseCategory, $alsoImport) {
            foreach ($rows as $i => $row) {
                $chosen = $row['status'] === 'ok' || ($row['status'] === 'duplicate' && in_array((string) $i, array_map('strval', $alsoImport), true) && ! $row['exact']);
                if (! $chosen) {
                    continue;
                }
                $meta = [
                    'effective_date' => $row['date'], 'description' => $row['description'] ?: __('savings::savings.imported'),
                    'reference' => $row['reference'] ?: null, 'idempotency_key' => 'import:'.$row['hash'],
                    'import_hash' => $row['hash'], 'import_batch_id' => $batch->id, 'source' => 'import', 'allow_duplicate' => true,
                ];
                try {
                    $row['amount'] > 0
                        ? $this->operations->income($account, $row['amount'], $incomeCategory, $meta)
                        : $this->operations->expense($account, -$row['amount'], $expenseCategory, $meta);
                    $rows[$i]['status'] = 'imported';
                    $imported++;
                } catch (LedgerException $e) {
                    $rows[$i]['status'] = 'invalid';
                    $rows[$i]['error'] = $e->getMessage();
                }
            }

            $batch->forceFill(['rows' => $rows, 'status' => 'done', 'imported' => $imported])->save();
        });

        audit('savings_import', __('savings::savings.audit_import', ['count' => $imported, 'file' => $batch->filename]), $batch, [], $batch->business_id);

        return $batch;
    }

    private function checkRow(Account $account, array $raw, int $line): array
    {
        $row = ['line' => $line, 'description' => trim((string) ($raw['description'] ?? $raw['details'] ?? $raw['narration'] ?? '')), 'reference' => trim((string) ($raw['reference'] ?? $raw['ref'] ?? '')), 'exact' => false];

        try {
            $row['date'] = Carbon::parse(trim((string) ($raw['date'] ?? '')))->toDateString();
            $amount = $raw['amount'] ?? null;
            if (($amount === null || $amount === '') && (isset($raw['credit']) || isset($raw['debit']))) {
                $credit = trim((string) ($raw['credit'] ?? '')) ?: '0';
                $debit = trim((string) ($raw['debit'] ?? '')) ?: '0';
                $row['amount'] = Money::parse($credit, $account->currency) - Money::parse($debit, $account->currency);
            } else {
                $row['amount'] = Money::parse((string) $amount, $account->currency);
            }
            if ($row['amount'] === 0) {
                throw new \InvalidArgumentException(__('savings::savings.amount_invalid'));
            }
            if ($row['date'] < $account->opening_date->toDateString()) {
                throw new \InvalidArgumentException(__('savings::savings.before_opening', ['name' => $account->name, 'date' => $account->opening_date->format('d/m/Y')]));
            }
        } catch (\Throwable $e) {
            return $row + ['status' => 'invalid', 'error' => $e->getMessage(), 'hash' => null, 'amount' => 0, 'date' => null];
        }

        $row['hash'] = hash('sha256', implode('|', [$account->id, $row['date'], $row['amount'], $row['reference'], mb_strtolower($row['description'])]));

        // Exactly this row was imported before: never again (AT-12).
        if (Transaction::query()->where('import_hash', $row['hash'])->exists()) {
            return array_merge($row, ['status' => 'duplicate', 'exact' => true, 'error' => __('savings::savings.import_already')]);
        }

        // Looks like something already typed in.
        $matches = $this->ledger->likelyDuplicates([
            'effective_date' => $row['date'], 'reference' => $row['reference'] ?: null,
            'entries' => [['account' => $account, 'amount' => $row['amount']]],
        ]);

        return array_merge($row, $matches->isNotEmpty()
            ? ['status' => 'duplicate', 'error' => __('savings::savings.import_looks_like', ['description' => $matches->first()->description])]
            : ['status' => 'ok']);
    }
}
