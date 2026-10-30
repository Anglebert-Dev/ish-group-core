<?php

namespace Modules\Savings\Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use Illuminate\Http\UploadedFile;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Budget;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\Commitment;
use Modules\Savings\Models\Expected;
use Modules\Savings\Models\Goal;
use Modules\Savings\Models\Obligation;
use Modules\Savings\Models\RecurringRule;
use Modules\Savings\Models\Transaction;

/**
 * "Fill from a file": the example file fills everything, uploading it again adds nothing,
 * and a file with problems saves nothing.
 */
class JsonImportTest extends SavingsTestCase
{
    private function template(): array
    {
        return json_decode(file_get_contents(module_path('Savings', 'resources/templates/finances-template.json')), true);
    }

    private function upload(array $data)
    {
        return $this->post(route('savings.data.fill'), [
            'file' => UploadedFile::fake()->createWithContent('finances.json', json_encode($data)),
        ]);
    }

    private function balanceOf(string $name): int
    {
        return $this->balance(Account::query()->where('name', $name)->firstOrFail());
    }

    public function test_example_files_download(): void
    {
        $this->get(route('savings.data.index'))->assertOk()
            ->assertSee(route('savings.data.template', 'finances'))
            ->assertSee(route('savings.data.template', 'statement'));

        foreach (['finances', 'statement', 'statement-debit-credit'] as $name) {
            $this->get(route('savings.data.template', $name))->assertOk()->assertDownload();
        }
        $this->get(route('savings.data.template', 'nope'))->assertNotFound();
    }

    public function test_the_example_file_fills_everything_and_a_second_upload_adds_nothing(): void
    {
        $this->upload($this->template())->assertSessionHas('success')->assertSessionMissing('savings_json_errors');

        $this->assertSame(4, Account::query()->count());
        $this->assertSame(11, Transaction::query()->count());
        $this->assertSame(35000 - 25600 + 4500 + 50000 - 30000 + 10000, $this->balanceOf('MoMo'));
        $this->assertSame(20000 - 10000 - 1000, $this->balanceOf('Cash'));
        $this->assertSame(500000 - 150000, $this->balanceOf('Savings'));

        $uncle = Obligation::query()->where('counterparty', 'Uncle Jean')->firstOrFail();
        $this->assertSame(200000, $this->obligations()->outstanding($uncle));
        $this->assertSame(15000, $this->obligations()->outstanding(Obligation::query()->where('counterparty', 'Eric')->firstOrFail()));
        $this->assertSame(0, $this->obligations()->outstanding(Obligation::query()->where('counterparty', 'Alice')->firstOrFail()));
        $this->assertSame(150000, $this->obligations()->outstanding(Obligation::query()->where('direction', 'restore')->firstOrFail()));

        $this->assertNotNull(Category::query()->where('name', 'Groceries')->value('parent_id'), 'sub-category under Food');
        $this->assertSame(2, Budget::query()->count());
        $this->assertSame(200000, $this->planning()->earmarked(Goal::query()->firstOrFail()));
        $this->assertSame(2, RecurringRule::query()->count());
        $this->assertSame(2, Expected::query()->where('description', 'Year-end bonus')->count());
        $this->assertSame(1, Commitment::query()->count());
        $this->assertSame(28, app(\Modules\Savings\Services\Periods::class)->rule()['start_day']);
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('action', 'savings_json_import')->exists());

        $counts = fn () => [Account::count(), Transaction::count(), Obligation::count(), Budget::count(), Goal::count(), RecurringRule::count(), Expected::count(), Commitment::count(), Category::count()];
        $before = $counts();
        $this->upload($this->template())->assertSessionHas('success');
        $this->assertSame($before, $counts(), 'nothing added twice');
        $this->assertSame(500000 - 150000, $this->balanceOf('Savings'));
    }

    public function test_a_file_with_problems_saves_nothing_and_says_where(): void
    {
        $data = $this->template();
        $data['transactions'][1]['account'] = 'Unknown bank';
        $data['transactions'][2]['date'] = '03/02/2026';
        $data['accounts'][0]['type'] = 'piggy';

        $this->upload($data)->assertSessionHas('error');
        $errors = implode("\n", session('savings_json_errors'));
        $this->assertStringContainsString('transactions[1].account', $errors);
        $this->assertStringContainsString('transactions[2].date', $errors);
        $this->assertStringContainsString('accounts[0].type', $errors);

        $this->assertSame(0, Account::query()->count());
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, Category::query()->count());
    }

    public function test_other_files_are_refused(): void
    {
        $this->upload(['tables' => []])->assertSessionHas('savings_json_errors');
        $this->post(route('savings.data.fill'), ['file' => UploadedFile::fake()->createWithContent('x.json', '{"format": "ish-finance",')])
            ->assertSessionHas('error');
        $this->assertSame(0, Account::query()->count());
    }
}
