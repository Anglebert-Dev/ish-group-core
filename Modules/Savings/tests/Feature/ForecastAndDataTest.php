<?php

namespace Modules\Savings\Tests\Feature;

use Modules\Savings\Models\Account;
use Modules\Savings\Models\Entry;
use Modules\Savings\Models\Expected;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Services\DataTransfer;
use Modules\Savings\Services\Forecast;
use Modules\Savings\Services\Reconciler;

class ForecastAndDataTest extends SavingsTestCase
{
    public function test_at15_scenario13_forecast_flags_shortfall_with_causes_without_touching_ledger(): void
    {
        $bank = $this->account('Bank', 200000, ['minimum_balance' => 10000]);
        $rent = $this->category('Rent');
        $salary = $this->category('Salary', 'income');
        $this->planning()->expect(['kind' => 'expense', 'description' => 'Rent', 'account_id' => $bank->id, 'category_id' => $rent->id, 'amount' => 250000, 'expected_date' => $this->day(5), 'confidence' => 'confirmed']);
        $salaryEvent = $this->planning()->expect(['kind' => 'income', 'description' => 'Salary part 2', 'account_id' => $bank->id, 'category_id' => $salary->id, 'amount' => 550000, 'expected_date' => $this->day(10), 'confidence' => 'expected']);
        $this->planning()->commit($bank, 'School fees', 30000, $this->day(3));

        $row = app(Forecast::class)->run(collect([$bank]), 30)[$bank->id];

        $this->assertSame($this->day(5), $row['shortfall']);
        $this->assertSame(-80000, $row['lowest'], '200k − 30k − 250k');
        $this->assertSame(['Rent', 'School fees'], collect($row['causes'])->pluck('label')->all());
        $this->assertSame(470000, $row['end']);
        $this->assertSame(200000, $this->balance($bank), 'forecast never changes the ledger');
        $this->assertSame(0, Transaction::query()->count());

        // What if the salary came earlier? Not saved.
        $what = app(Forecast::class)->run(collect([$bank]), 30, [$salaryEvent->uuid => ['date' => $this->day(4)]])[$bank->id];
        $this->assertNull($what['shortfall']);
        $this->assertSame($this->day(10), $salaryEvent->fresh()->expected_date->toDateString());
    }

    public function test_at05_expected_income_appears_in_forecast_not_balance(): void
    {
        $bank = $this->account('Bank', 1000);
        $salary = $this->category('Salary', 'income');
        $this->planning()->expect(['kind' => 'income', 'description' => 'Salary', 'account_id' => $bank->id, 'category_id' => $salary->id, 'amount' => 5000, 'expected_date' => $this->day(2)]);

        $row = app(Forecast::class)->run(collect([$bank]), 10)[$bank->id];
        $this->assertSame(6000, $row['end']);
        $this->assertSame(1000, $this->balance($bank));
        $this->assertSame('expected', $row['events'][0]['status']);
    }

    public function test_at13_scenario12_reconciliation_difference_stays_visible(): void
    {
        $bank = $this->account('Bank', 100000);
        $fees = $this->category('Bank fees');
        $this->ops()->expense($bank, 20000, $fees, ['effective_date' => $this->day(-2)]);

        $rec = app(Reconciler::class)->check($bank, $this->day(), 78500);
        $this->assertSame('open', $rec->status);
        $this->assertSame(-1500, $rec->difference);
        $this->assertSame(80000, $this->balance($bank), 'nothing silently corrected');

        app(Reconciler::class)->explain($rec, 'Card payment still pending');
        $this->assertSame('explained', $rec->fresh()->status);
        $this->assertSame(-1500, $rec->fresh()->difference, 'still shown');

        app(Reconciler::class)->adjust($rec->fresh(), 'Monthly SMS fees not recorded');
        $this->assertSame(78500, $this->balance($bank));
        $this->assertSame('adjusted', $rec->fresh()->status);
        $this->assertSame('matched', app(Reconciler::class)->check($bank, $this->day(), 78500)->status);
        $this->assertSame(2, \Modules\Savings\Models\Reconciliation::query()->count(), 'history kept');
    }

    public function test_at12_import_flags_duplicates_and_never_imports_twice(): void
    {
        $bank = $this->account('Bank', 500000);
        $food = $this->category('Food');
        $salary = $this->category('Salary', 'income');
        $this->ops()->expense($bank, 12000, $food, ['effective_date' => $this->day(-1), 'description' => 'Supermarket']);

        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csv, "Date,Description,Amount,Reference\n"
            .$this->day(-1).",Supermarket,-12000,\n"
            .$this->day().",Salary,450000,SAL-10\n"
            .$this->day().",Lunch,-3500,\n"
            ."not-a-date,Broken,12,\n");

        $batch = app(DataTransfer::class)->preview($bank, $csv, 'october.csv');
        $this->assertSame(['duplicate', 'ok', 'ok', 'invalid'], collect($batch->rows)->pluck('status')->all());

        app(DataTransfer::class)->commit($batch, $salary, $food);
        $this->assertSame(500000 - 12000 + 450000 - 3500, $this->balance($bank));

        // Same file again: everything already imported.
        $again = app(DataTransfer::class)->preview($bank, $csv, 'october.csv');
        $this->assertSame(['duplicate', 'duplicate', 'duplicate', 'invalid'], collect($again->rows)->pluck('status')->all());
        $this->assertSame([false, true, true], collect($again->rows)->take(3)->pluck('exact')->all(), 'row 1 only looks like the typed one; rows 2–3 were imported');
        app(DataTransfer::class)->commit($again, $salary, $food, [1, 2]);
        $this->assertSame(934500, $this->balance($bank), 'exact re-imports are never posted, even when ticked');
    }

    public function test_at16_backup_and_restore_preserve_everything_without_duplication(): void
    {
        $bank = $this->account('Bank', 100000);
        $savings = $this->account('Savings', 0, ['type' => 'savings']);
        $food = $this->category('Food');
        $tx = $this->ops()->expense($bank, 5000, $food, ['effective_date' => $this->day()]);
        $this->ops()->transfer($bank, $savings, 20000, ['effective_date' => $this->day()]);
        $this->ledger()->reverse($tx, 'Wrong');
        $owed = $this->obligations()->create(['direction' => 'receivable', 'counterparty' => 'Jean', 'description' => 'Jean', 'currency' => 'RWF'], 30000);
        $this->obligations()->repay($owed, $bank, 10000, ['effective_date' => $this->day()]);

        $backup = json_decode(json_encode(app(DataTransfer::class)->export($this->business->id)), true);
        $this->assertSame([], app(DataTransfer::class)->validate($backup));

        // Merge into the same data: nothing added.
        $result = app(DataTransfer::class)->restore($this->business->id, $backup, 'merge');
        $this->assertSame([], $result['added']);
        $this->assertSame(90000, $this->balance($bank));

        // Replace: wipe and load back, same position.
        app(DataTransfer::class)->restore($this->business->id, $backup, 'replace');
        $restoredBank = Account::query()->where('uuid', $bank->uuid)->firstOrFail();
        $this->assertSame(90000, $this->balance($restoredBank));
        $this->assertSame(20000, $this->balance(Account::query()->where('uuid', $savings->uuid)->firstOrFail()));
        $this->assertSame(4, Transaction::query()->count());
        $this->assertSame(20000, $this->obligations()->outstanding(Obligation::query()->firstOrFail()));
        $this->assertNotNull(Transaction::query()->where('uuid', $tx->uuid)->first()->reversal);

        // A broken file is refused before anything changes.
        $broken = $backup;
        $broken['tables']['sav_entries'][0]['account_id'] = 'nope';
        $this->assertNotEmpty(app(DataTransfer::class)->validate($broken));
        $this->expectException(\Modules\Savings\Exceptions\LedgerException::class);
        app(DataTransfer::class)->restore($this->business->id, $broken, 'replace');
    }

    public function test_finance_data_is_private_to_each_business(): void
    {
        $bank = $this->account('Bank', 1000);
        [$other] = $this->makeBusiness();
        app(\App\Support\Tenancy\CurrentBusiness::class)->set($other);

        $this->assertSame(0, Account::query()->count());
        $this->assertSame(0, Expected::query()->count());
        $this->assertSame(0, Entry::query()->count());
    }
}
