<?php

namespace Modules\InjongePay\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\FeeAccrual;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\StatementCheck as Check;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Money;

/**
 * GTBank has no balance or statement API, so its statement of the platform's
 * settlement account is uploaded (CSV) and compared with the books:
 *  - credits ↔ our payments (by GTBank reference in the line, else same amount and day)
 *  - debits  ↔ our paid cash-outs (by our CSH reference or GTBank's receipt, else amount and day)
 *  - closing balance ↔ what the books say GTBank holds
 * Columns are recognised by their headings, whatever order GTBank puts them in.
 */
class StatementCheck
{
    private const HEADINGS = [
        'date' => ['value date', 'transaction date', 'posting date', 'trans date', 'date'],
        'text' => ['narration', 'description', 'details', 'remarks', 'particulars', 'reference', 'ref'],
        'credit' => ['credit', 'deposit', 'cr amount', 'cr'],
        'debit' => ['debit', 'withdrawal', 'dr amount', 'dr'],
        'amount' => ['amount'],
        'balance' => ['closing balance', 'running balance', 'balance'],
    ];

    public function __construct(protected ReconciliationItems $items)
    {
    }

    public function run(string $path, string $fileName, CarbonInterface $from, CarbonInterface $to): Check
    {
        [$columns, $lines] = $this->read($path);
        $from = Carbon::parse($from->toDateString())->startOfDay();
        $to = Carbon::parse($to->toDateString())->endOfDay();
        $lines = array_values(array_filter($lines, fn ($l) => $l['date'] === null || ($l['date']->between($from, $to))));

        $payments = Payment::query()->withoutGlobalScopes()->where('payment_status', '!=', Payment::STATUS_REVERSED)
            ->whereBetween('created_at', [$from, $to])->get(['id', 'bank_reference', 'gross_amount', 'created_at', 'value_date']);
        $cashouts = Cashout::query()->withoutGlobalScopes()->where('status', Cashout::COMPLETED)
            ->whereBetween('completed_at', [$from, $to])->get(['id', 'reference', 'cbs_reference', 'amount', 'completed_at']);

        [$creditsLeft, $paymentsLeft] = $this->match(array_filter($lines, fn ($l) => $l['credit'] > 0), $payments, 'credit',
            fn ($p) => [$p->bank_reference], fn ($p) => $p->gross_amount, fn ($p) => ($p->value_date ?? $p->created_at)->toDateString());
        [$debitsLeft, $cashoutsLeft] = $this->match(array_filter($lines, fn ($l) => $l['debit'] > 0), $cashouts, 'debit',
            fn ($c) => array_filter([$c->reference, $c->cbs_reference]), fn ($c) => $c->amount, fn ($c) => $c->completed_at->toDateString());

        $closing = collect($lines)->whereNotNull('balance')->last()['balance'] ?? null;
        $books = $this->booksHold();

        $summary = [
            'columns' => $columns,
            'bank_credits' => array_sum(array_column($lines, 'credit')), 'bank_credit_lines' => count(array_filter($lines, fn ($l) => $l['credit'] > 0)),
            'our_payments' => (int) $payments->sum('gross_amount'), 'our_payment_count' => $payments->count(),
            'bank_debits' => array_sum(array_column($lines, 'debit')), 'bank_debit_lines' => count(array_filter($lines, fn ($l) => $l['debit'] > 0)),
            'our_cashouts' => (int) $cashouts->sum('amount'), 'our_cashout_count' => $cashouts->count(),
            'bank_closing' => $closing, 'books_hold' => $books,
        ];

        $unmatched = [
            'bank_credits' => array_values($creditsLeft),
            'our_payments' => $paymentsLeft->map(fn ($p) => ['reference' => $p->bank_reference, 'amount' => $p->gross_amount, 'date' => $p->created_at->toDateString()])->values()->all(),
            'bank_debits' => array_values($debitsLeft),
            'our_cashouts' => $cashoutsLeft->map(fn ($c) => ['reference' => $c->reference, 'amount' => $c->amount, 'date' => $c->completed_at->toDateString()])->values()->all(),
        ];

        $agrees = array_filter($unmatched) === [] && ($closing === null || $closing === $books);

        $check = Check::query()->create([
            'period_from' => $from->toDateString(), 'period_to' => $to->toDateString(), 'file_name' => $fileName, 'lines' => count($lines),
            'summary' => $summary, 'unmatched' => array_map(fn ($rows) => array_map(fn ($r) => array_map(fn ($v) => $v instanceof CarbonInterface ? $v->toDateString() : $v, $r), $rows), $unmatched),
            'agrees' => $agrees, 'uploaded_by' => auth()->id(), 'uploaded_by_name' => auth()->user()?->name,
        ]);

        audit('injongepay_statement_checked', __('injongepay::injongepay.audit_statement', ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
            $check, ['agrees' => $agrees, 'lines' => count($lines)]);

        if (! $agrees) {
            $this->items->open('statement_difference', $check->uuid, null, $check, $closing !== null ? $closing - $books : null, [
                'period' => $from->toDateString().' – '.$to->toDateString(),
                'unmatched' => array_map('count', $unmatched),
            ]);
        }

        return $check;
    }

    /** What GTBank should be holding for us: every balance, fees not swept yet, and cash-outs not sent or not settled yet. */
    public function booksHold(): int
    {
        return (int) Wallet::query()->withoutGlobalScopes()->sum(\Illuminate\Support\Facades\DB::raw('pending_balance + available_balance'))
            + (int) FeeAccrual::query()->whereNull('swept_at')->whereNull('cancelled_at')->sum('amount')
            + (int) Cashout::query()->withoutGlobalScopes()->whereIn('status', Cashout::OPEN)->sum('amount');
    }

    /**
     * @return array{0: array, 1: \Illuminate\Support\Collection} bank lines left, our records left
     */
    private function match(array $lines, $records, string $side, callable $refs, callable $amount, callable $day): array
    {
        $left = $records->keyBy('id');

        // First by reference found in the line's text.
        foreach ($lines as $i => $line) {
            foreach ($left as $id => $record) {
                foreach ($refs($record) as $ref) {
                    if ($ref !== '' && stripos($line['text'], (string) $ref) !== false && $line[$side] === $amount($record)) {
                        unset($lines[$i], $left[$id]);

                        continue 3;
                    }
                }
            }
        }

        // Then the same amount on the same day.
        foreach ($lines as $i => $line) {
            foreach ($left as $id => $record) {
                if ($line[$side] === $amount($record) && $line['date']?->toDateString() === $day($record)) {
                    unset($lines[$i], $left[$id]);

                    continue 2;
                }
            }
        }

        return [$lines, $left->values()];
    }

    /** @return array{0: array<string, string>, 1: list<array{date: ?Carbon, text: string, credit: int, debit: int, balance: ?int}>} */
    private function read(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new InjongePayException(__('injongepay::injongepay.err_statement_read'));
        }

        $header = null;
        $map = [];
        $lines = [];

        while (($row = fgetcsv($handle, 0, $this->delimiter($path))) !== false) {
            if ($header === null) {
                // The heading row is the first one naming a date and an amount column (statements often start with a title block).
                $candidate = $this->columns(array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), $row));
                if (isset($candidate['date']) && (isset($candidate['credit']) || isset($candidate['amount']))) {
                    $header = $row;
                    $map = $candidate;
                }

                continue;
            }

            $get = fn (string $key) => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';
            $credit = $this->money($get('credit'));
            $debit = $this->money($get('debit'));

            if (! isset($map['credit']) && isset($map['amount'])) {
                $signed = $this->money($get('amount'), signed: true);
                [$credit, $debit] = $signed >= 0 ? [$signed, 0] : [0, -$signed];
            }

            if ($credit === 0 && $debit === 0) {
                continue;
            }

            $lines[] = [
                'date' => rescue(fn () => $get('date') !== '' ? Carbon::parse(str_replace('/', '-', $get('date'))) : null, null, false),
                'text' => $get('text'),
                'credit' => $credit,
                'debit' => $debit,
                'balance' => $get('balance') !== '' ? $this->money($get('balance'), signed: true) : null,
            ];
        }

        fclose($handle);

        if ($header === null) {
            throw new InjongePayException(__('injongepay::injongepay.err_statement_columns'));
        }

        return [array_map(fn ($i) => (string) $header[$i], $map), $lines];
    }

    /** @param list<string> $headings */
    private function columns(array $headings): array
    {
        $map = [];

        foreach (self::HEADINGS as $key => $names) {
            foreach ($names as $name) {
                foreach ($headings as $i => $heading) {
                    if (! in_array($i, $map, true) && ($heading === $name || str_starts_with($heading, $name.' ') || str_starts_with($heading, $name.'('))) {
                        $map[$key] = $i;

                        continue 3;
                    }
                }
            }
        }

        return $map;
    }

    private function delimiter(string $path): string
    {
        $first = (string) fgets(fopen($path, 'r'));

        return substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
    }

    /** "1,250.00", "RWF 1 250", "(500.00)" → cents. */
    private function money(string $value, bool $signed = false): int
    {
        $negative = str_starts_with(trim($value), '-') || (str_starts_with(trim($value), '(') && str_ends_with(trim($value), ')'));
        $clean = preg_replace('/[^0-9.]/', '', str_replace(',', '', $value)) ?? '';

        if ($clean === '' || ! is_numeric($clean)) {
            return 0;
        }

        $cents = Money::toCents(number_format((float) $clean, 2, '.', ''));

        return $signed && $negative ? -$cents : abs($cents);
    }
}
