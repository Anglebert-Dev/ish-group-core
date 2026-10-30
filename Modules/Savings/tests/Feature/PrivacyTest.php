<?php

namespace Modules\Savings\Tests\Feature;

use App\Models\Notification\Notification;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Transaction;
use Modules\Savings\Services\DataTransfer;
use Modules\Savings\Services\Periods;
use Modules\Savings\Support\CurrentOwner;

/**
 * Finances are private: two people in the same business never see each other's.
 */
class PrivacyTest extends SavingsTestCase
{
    public function test_each_user_sees_only_their_own_finances(): void
    {
        $mine = $this->account('My bank', 100000);
        $this->ops()->expense($mine, 5000, $this->category('Food'), ['effective_date' => $this->day()]);

        $colleague = $this->makeMember($this->business, ['list savings.overview', 'list savings.account', 'read savings.account', 'create savings.account', 'list savings.transaction', 'read savings.transaction']);
        $this->flushSession();
        $this->actingAs($colleague, 'staff');
        app(CurrentOwner::class)->set(null);

        $this->assertSame(0, Account::query()->count(), 'none of my accounts');
        $this->assertSame(0, Transaction::query()->count());
        $this->get(route('savings.accounts.index'))->assertOk()->assertDontSee('My bank');
        $this->get(route('savings.accounts.show', $mine))->assertNotFound();
        $this->get(route('savings.transactions.index'))->assertOk()->assertDontSee('Food');

        // Their own account is theirs alone.
        $this->post(route('savings.accounts.store'), ['name' => 'Colleague bank', 'type' => 'bank', 'currency' => 'RWF', 'opening_date' => $this->day(-1), 'opening_balance' => '7000', 'is_liquid' => 1]);
        $this->assertSame($colleague->id, Account::query()->firstOrFail()->owner_id);

        $this->flushSession();
        $this->actingAs($this->owner, 'staff');
        $this->assertSame(['My bank'], Account::query()->pluck('name')->all(), 'the business owner does not see it either');
    }

    public function test_one_person_cannot_post_to_another_persons_account(): void
    {
        $mine = $this->account('Mine', 1000);
        $colleague = $this->makeMember($this->business);
        app(CurrentOwner::class)->set($colleague->id);
        $theirs = $this->account('Theirs', 1000);

        $this->expectException(\Modules\Savings\Exceptions\LedgerException::class);
        $this->ops()->transfer($theirs, $mine, 500, ['effective_date' => $this->day()]);
    }

    public function test_settings_are_personal(): void
    {
        $this->put(route('savings.settings.update'), ['currency' => 'RWF', 'period_type' => 'monthly', 'start_day' => 28, 'weekday' => 1, 'anchor' => $this->day()])->assertSessionHas('success');
        $this->assertSame(28, app(Periods::class)->rule()['start_day']);

        $colleague = $this->makeMember($this->business);
        app()->forgetScopedInstances();
        app(\App\Support\Tenancy\CurrentBusiness::class)->set($this->business);
        app(CurrentOwner::class)->set($colleague->id);
        $this->assertSame(1, app(Periods::class)->rule()['start_day'], 'the colleague keeps the default');
    }

    public function test_backup_holds_only_my_finances_and_alerts_go_only_to_me(): void
    {
        $this->account('Mine', 1000);
        $colleague = $this->makeMember($this->business);
        app(CurrentOwner::class)->set($colleague->id);
        $theirs = $this->account('Theirs', 1000);
        $this->planning()->expect(['kind' => 'expense', 'description' => 'Their rent', 'account_id' => $theirs->id, 'category_id' => $this->category('Rent')->id, 'amount' => 5000, 'expected_date' => $this->day(1)]);
        app(CurrentOwner::class)->set(null);

        $backup = app(DataTransfer::class)->export($this->business->id, $this->owner->id);
        $this->assertSame(['Mine'], array_column($backup['tables']['sav_accounts'], 'name'));

        $this->artisan('savings:alerts')->assertSuccessful();
        $this->assertTrue(Notification::query()->where('user_id', $colleague->id)->where('body', 'like', '%Their rent%')->exists());
        $this->assertFalse(Notification::query()->where('user_id', $this->owner->id)->where('body', 'like', '%Their rent%')->exists());
    }

    public function test_reset_erases_only_my_finances(): void
    {
        $bank = $this->account('My bank', 100000);
        $this->ops()->expense($bank, 5000, $this->category('Food'), ['effective_date' => $this->day()]);
        $this->planning()->commit($bank, 'Rent', 1000);
        $this->put(route('savings.settings.update'), ['currency' => 'RWF', 'period_type' => 'monthly', 'start_day' => 28, 'weekday' => 1, 'anchor' => $this->day()]);

        $colleague = $this->makeMember($this->business);
        app(CurrentOwner::class)->set($colleague->id);
        $this->account('Colleague bank', 5000);
        app(CurrentOwner::class)->set(null);

        $this->withConfirmedPassword()->post(route('savings.settings.reset'), ['confirm' => 'reset'])->assertSessionHasErrors('confirm');
        $this->assertSame(1, Account::query()->count(), 'nothing erased without typing RESET');

        $this->withConfirmedPassword()->post(route('savings.settings.reset'), ['confirm' => 'RESET'])->assertRedirect(route('savings.overview'));

        $this->assertSame(0, Account::query()->count());
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('sav_entries')->where('owner_id', $this->owner->id)->count());
        $this->assertSame(1, app(Periods::class)->rule()['start_day'], 'my settings are back to the default');

        app(CurrentOwner::class)->set($colleague->id);
        $this->assertSame(['Colleague bank'], Account::query()->pluck('name')->all(), 'the colleague keeps theirs');
    }
}
