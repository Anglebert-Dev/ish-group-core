<?php

namespace Modules\Savings\Tests\Feature;

use App\Models\Notification\Notification;
use Illuminate\Http\UploadedFile;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\Expected;
use Modules\Savings\Models\Goal;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\Reconciliation;
use Modules\Savings\Models\Transaction;

class SavingsScreensTest extends SavingsTestCase
{
    private function setUpData(): array
    {
        $this->post(route('savings.categories.starter'))->assertSessionHas('success');
        $this->post(route('savings.accounts.store'), ['name' => 'Bank of Kigali', 'type' => 'bank', 'currency' => 'RWF', 'opening_date' => $this->day(-30), 'opening_balance' => '500,000', 'minimum_balance' => '20000', 'is_liquid' => 1])->assertRedirect();
        $this->post(route('savings.accounts.store'), ['name' => 'Savings', 'type' => 'savings', 'currency' => 'RWF', 'opening_date' => $this->day(-30), 'opening_balance' => '1000000', 'is_liquid' => 0]);
        $this->post(route('savings.accounts.store'), ['name' => 'MoMo', 'type' => 'mobile_money', 'currency' => 'RWF', 'opening_date' => $this->day(-30), 'opening_balance' => '0', 'is_liquid' => 1]);

        return [
            Account::query()->where('name', 'Bank of Kigali')->firstOrFail(),
            Account::query()->where('name', 'Savings')->firstOrFail(),
            Account::query()->where('name', 'MoMo')->firstOrFail(),
            Category::query()->where('name', 'Food & groceries')->firstOrFail(),
            Category::query()->where('name', 'Salary')->firstOrFail(),
        ];
    }

    private function tx(array $data)
    {
        return $this->post(route('savings.transactions.store'), $data + ['idempotency_key' => \Illuminate\Support\Str::uuid()->toString(), 'effective_date' => $this->day()]);
    }

    public function test_every_screen_opens_and_the_main_forms_work(): void
    {
        [$bank, $savings, $momo, $food, $salary] = $this->setUpData();

        $this->tx(['type' => 'expense', 'account' => $bank->uuid, 'amount' => '12,500', 'category' => $food->uuid, 'description' => 'Supermarket'])->assertRedirect()->assertSessionHas('success');
        $this->tx(['type' => 'income', 'account' => $bank->uuid, 'amount' => '450000', 'category' => $salary->uuid, 'description' => 'Salary part 1'])->assertSessionHas('success');
        $this->tx(['type' => 'transfer', 'account' => $savings->uuid, 'to_account' => $momo->uuid, 'amount' => '100000', 'description' => 'To phone'])->assertSessionHas('success');
        $this->tx(['type' => 'split', 'account' => $bank->uuid, 'amount' => '25600', 'owed_back' => '225000', 'category' => $food->uuid, 'counterparty' => 'Jean', 'description' => 'Overpaid'])->assertSessionHas('success');
        $this->tx(['type' => 'advance', 'account' => $bank->uuid, 'amount' => '100000', 'counterparty' => 'Eric', 'due_date' => $this->day(10)])->assertSessionHas('success');
        $this->assertSame(500000 - 12500 + 450000 - 250600 - 100000, $this->balance($bank));

        // Same purchase typed again: warned, then confirmed.
        $this->tx(['type' => 'expense', 'account' => $bank->uuid, 'amount' => '12500', 'category' => $food->uuid, 'description' => 'Supermarket'])
            ->assertSessionHas('error')->assertSessionHas('savings_duplicates');
        $this->tx(['type' => 'expense', 'account' => $bank->uuid, 'amount' => '12500', 'category' => $food->uuid, 'description' => 'Supermarket', 'allow_duplicate' => 1])->assertSessionHas('success');

        // Reverse and correct.
        $wrong = Transaction::query()->where('description', 'Supermarket')->latest('id')->firstOrFail();
        $this->withConfirmedPassword()->post(route('savings.transactions.reverse', $wrong), ['reason' => 'Entered twice', 'correct' => 1])
            ->assertRedirect(route('savings.transactions.create', ['replaces' => $wrong->uuid]));
        $this->get(route('savings.transactions.create', ['replaces' => $wrong->uuid]))->assertOk()->assertSee('Supermarket');

        // Refund.
        $first = Transaction::query()->where('description', 'Supermarket')->orderBy('id')->firstOrFail();
        $this->post(route('savings.transactions.refund', $first), ['account' => $bank->uuid, 'amount' => '2500', 'effective_date' => $this->day(), 'idempotency_key' => 'r1'])->assertRedirect();

        // Planning: instalments, mark received, recurring, commitment.
        $this->post(route('savings.planning.expected.store'), ['kind' => 'income', 'description' => 'Salary October', 'account' => $bank->uuid, 'category' => $salary->uuid, 'confidence' => 'confirmed',
            'parts' => [['date' => $this->day(), 'amount' => '450000'], ['date' => $this->day(12), 'amount' => '550000']]])->assertSessionHas('success');
        $part = Expected::query()->orderBy('expected_date')->firstOrFail();
        $this->post(route('savings.planning.expected.post', $part), ['amount' => '450000', 'effective_date' => $this->day()])->assertSessionHas('success');
        $this->post(route('savings.planning.rules.store'), ['kind' => 'expense', 'description' => 'Internet', 'account' => $bank->uuid, 'category' => Category::query()->where('name', 'Internet & phone')->value('uuid'),
            'amount' => '30000', 'frequency' => 'month', 'interval' => 1, 'start_date' => $this->day(3), 'confidence' => 'confirmed'])->assertSessionHas('success');
        $this->post(route('savings.planning.commitments.store'), ['account' => $bank->uuid, 'description' => 'Ikimina', 'amount' => '50000', 'due_date' => $this->day(5)])->assertSessionHas('success');

        // Obligations: restore target and repayment.
        $this->post(route('savings.obligations.store'), ['direction' => 'restore', 'description' => 'Put back into savings', 'account' => $savings->uuid, 'currency' => 'RWF'])->assertRedirect();
        $restore = Obligation::query()->where('direction', 'restore')->firstOrFail();
        $out = Transaction::query()->where('description', 'To phone')->firstOrFail();
        $this->post(route('savings.obligations.basis', $restore), ['transaction' => $out->uuid, 'amount' => '100000'])->assertSessionHas('success');
        $this->post(route('savings.obligations.basis', $restore), ['transaction' => $out->uuid, 'amount' => '100000'])->assertSessionHas('error');
        $this->post(route('savings.obligations.repay', $restore), ['account' => $bank->uuid, 'amount' => '40000', 'effective_date' => $this->day(), 'idempotency_key' => 'rs1'])->assertSessionHas('success');
        $jean = Obligation::query()->where('counterparty', 'Jean')->firstOrFail();
        $this->post(route('savings.obligations.repay', $jean), ['account' => $bank->uuid, 'amount' => '100000', 'effective_date' => $this->day(), 'idempotency_key' => 'j1'])->assertSessionHas('success');

        // Goal, budget, reconcile, settings.
        $this->post(route('savings.goals.store'), ['name' => 'Emergency fund', 'target_amount' => '2,000,000', 'currency' => 'RWF', 'account' => $savings->uuid])->assertSessionHas('success');
        $goal = Goal::query()->firstOrFail();
        $this->post(route('savings.goals.allocate', $goal), ['amount' => '200000'])->assertSessionHas('success');
        $this->post(route('savings.goals.contribute', $goal), ['account' => $bank->uuid, 'amount' => '50000', 'effective_date' => $this->day(), 'idempotency_key' => 'g1'])->assertSessionHas('success');
        $this->put(route('savings.budgets.update'), ['date' => $this->day(), 'limits' => [$food->uuid => '100000']])->assertSessionHas('success');
        $this->post(route('savings.reconcile.store'), ['account' => $bank->uuid, 'statement_date' => $this->day(), 'statement_balance' => '1'])->assertRedirect();
        $this->put(route('savings.settings.update'), ['currency' => 'RWF', 'period_type' => 'monthly', 'start_day' => 28, 'weekday' => 1, 'anchor' => $this->day(), 'goal_earmarks' => 1, 'minimum_balance' => 1, 'alert_shortfalls' => 1])->assertSessionHas('success');

        // Every screen opens.
        foreach (['savings.overview', 'savings.accounts.index', 'savings.categories.index', 'savings.transactions.index', 'savings.transactions.create', 'savings.budgets.index',
            'savings.planning.index', 'savings.obligations.index', 'savings.goals.index', 'savings.forecast', 'savings.reconcile.index', 'savings.data.index', 'savings.settings.edit'] as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (\Modules\Savings\Services\Reports::KEYS as $report) {
            $this->get(route('savings.reports', ['report' => $report, 'from' => $this->day(-40), 'to' => $this->day(40)]))->assertOk();
        }
        $this->get(route('savings.accounts.show', $bank))->assertOk()->assertSee('Supermarket');
        $this->get(route('savings.transactions.show', $first))->assertOk();
        $this->get(route('savings.obligations.show', $restore))->assertOk()->assertSee('To phone');
        $this->get(route('savings.reconcile.show', Reconciliation::query()->firstOrFail()))->assertOk();
        $this->get(route('savings.forecast', ['w' => [Expected::query()->where('status', 'expected')->value('uuid') => ['date' => $this->day(1)]]]))->assertOk();
        $this->get(route('savings.transactions.export', ['format' => 'excel']))->assertOk();
        $this->get(route('savings.reports.export', ['format' => 'pdf', 'report' => 'spending']))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertSee(__('savings::savings.dashboard_available'));
    }

    public function test_import_backup_and_restore_through_the_screens(): void
    {
        [$bank, , , $food, $salary] = $this->setUpData();

        $csv = UploadedFile::fake()->createWithContent('statement.csv', "date,description,amount\n{$this->day()},Coffee,-2500\n{$this->day()},Refund shop,1000\n");
        $this->post(route('savings.data.preview'), ['account' => $bank->uuid, 'file' => $csv])->assertRedirect();
        $batch = \Modules\Savings\Models\ImportBatch::query()->firstOrFail();
        $this->get(route('savings.data.batch', $batch))->assertOk()->assertSee('Coffee');
        $this->post(route('savings.data.commit', $batch), ['income_category' => $salary->uuid, 'expense_category' => $food->uuid])->assertSessionHas('success');
        $this->assertSame(498500, $this->balance($bank));

        $backup = $this->withConfirmedPassword()->get(route('savings.data.backup'))->assertOk();
        $file = UploadedFile::fake()->createWithContent('backup.json', $backup->getContent());
        $this->withConfirmedPassword()->post(route('savings.data.restore'), ['file' => $file, 'mode' => 'merge'])->assertSessionHas('success');
        $this->assertSame(2, Transaction::query()->count(), 'merge adds nothing twice');
    }

    public function test_permissions_and_isolation(): void
    {
        [$bank] = $this->setUpData();

        $this->flushSession();
        $this->actingAs($this->makeMember($this->business, ['list savings.overview', 'list savings.transaction']), 'staff');
        $this->get(route('savings.overview'))->assertOk();
        $this->get(route('savings.transactions.create'))->assertForbidden();
        $this->get(route('savings.accounts.index'))->assertForbidden();

        $this->flushSession();
        $this->actingAsStaff();
        $this->get(route('savings.accounts.show', $bank))->assertNotFound();
    }

    public function test_daily_alerts_notify_once(): void
    {
        [$bank, , , $food] = $this->setUpData();
        $this->post(route('savings.planning.expected.store'), ['kind' => 'expense', 'description' => 'Rent', 'account' => $bank->uuid, 'category' => $food->uuid, 'confidence' => 'confirmed',
            'parts' => [['date' => $this->day(1), 'amount' => '600000']]]);

        $this->artisan('savings:alerts')->assertSuccessful();
        $count = Notification::query()->where('user_id', $this->owner->id)->count();
        $this->assertGreaterThanOrEqual(2, $count, 'bill due + shortfall');

        $this->artisan('savings:alerts')->assertSuccessful();
        $this->assertSame($count, Notification::query()->where('user_id', $this->owner->id)->count(), 'not sent twice');
    }
}
