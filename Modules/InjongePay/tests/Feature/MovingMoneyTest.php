<?php

namespace Modules\InjongePay\Tests\Feature;

use App\Notifications\BrandedNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Models\Transfer;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\Ledger;
use Modules\InjongePay\Services\Security\Devices;
use Modules\InjongePay\Services\Security\Pins;
use Modules\InjongePay\Services\SettlementAccounts;

class MovingMoneyTest extends InjongePayTestCase
{
    private Wallet $from;

    private Wallet $to;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->from = $this->wallet(overrides: ['name' => 'Till A']);
        $this->to = $this->wallet(overrides: ['name' => 'Till B']);
        app(Ledger::class)->post($this->from, LedgerEntry::CREDIT, LedgerEntry::AVAILABLE, 5000000, 'payment', 'seed');
        app(Pins::class)->set($this->owner, '48213');
        // Not "just set": the PIN alone is enough from a trusted browser.
        \Modules\InjongePay\Models\TransactionPin::query()->update(['set_at' => now()->subDays(3)]);
    }

    private function send(array $overrides = [])
    {
        return $this->post(route('injongepay.transfers.store'), $overrides + [
            'wallet' => $this->from->uuid, 'merchant_code' => $this->to->merchant_code, 'amount' => '10000', 'pin' => '48213',
        ]);
    }

    private function emailedCode(): string
    {
        $code = null;
        Notification::assertSentTo($this->owner, BrandedNotification::class, function (BrandedNotification $n) use (&$code) {
            preg_match('/\b(\d{6})\b/', json_encode($n), $m);
            $code = $m[1] ?? $code;

            return true;
        });

        return (string) $code;
    }

    public function test_a_weak_pin_is_refused_and_wrong_pins_lock_it(): void
    {
        $pins = app(Pins::class);
        foreach (['11111', '12345', '98765', '1234'] as $weak) {
            try {
                $pins->set($this->owner, $weak);
                $this->fail("{$weak} accepted");
            } catch (InjongePayException) {
            }
        }

        foreach ([1, 2, 3] as $try) {
            try {
                $pins->check($this->owner, '00000');
            } catch (InjongePayException) {
            }
        }

        $this->expectExceptionMessage('locked');
        $pins->check($this->owner, '48213');
    }

    public function test_from_a_new_browser_the_pin_needs_the_emailed_code_then_the_browser_is_trusted(): void
    {
        $this->asOwner();

        $this->send()->assertSessionHas('step_up');
        $this->assertSame(0, Transfer::query()->withoutGlobalScopes()->count());

        $response = $this->send(['code' => $this->emailedCode()])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(1, Transfer::query()->withoutGlobalScopes()->count());
        $this->assertSame([4000000, 1000000], [$this->from->fresh()->available_balance, $this->to->fresh()->available_balance]);
        $cookie = $response->getCookie(Devices::COOKIE, false);
        $this->assertNotNull($cookie);

        // Same browser: the PIN is enough.
        $this->withUnencryptedCookie(Devices::COOKIE, $cookie->getValue())->send(['amount' => '500'])->assertSessionHas('success');
        $this->assertSame(2, Transfer::query()->withoutGlobalScopes()->count());
    }

    public function test_a_wrong_pin_moves_nothing(): void
    {
        $this->asOwner()->send(['pin' => '00000'])->assertSessionHas('error');

        $this->assertSame(0, Approval::query()->withoutGlobalScopes()->count());
        $this->assertSame(5000000, $this->from->fresh()->available_balance);
    }

    public function test_more_than_the_balance_fails_and_nothing_moves(): void
    {
        $this->asOwner();
        $this->send(['amount' => '60000']);
        $this->send(['amount' => '60000', 'code' => $this->emailedCode()])->assertSessionHas('error');

        $this->assertSame(Approval::FAILED, Approval::query()->withoutGlobalScopes()->value('status'));
        $this->assertSame(5000000, $this->from->fresh()->available_balance);
        $this->assertSame(0, $this->to->fresh()->available_balance);
    }

    public function test_above_the_threshold_a_second_person_approves_with_their_own_pin(): void
    {
        $this->profile(overrides: ['second_approval_above' => 500000]);
        $approver = $this->makeMember($this->business, ['approve injongepay.transfer', 'list injongepay.transfer']);
        app(Pins::class)->set($approver, '90817');
        $this->asOwner();
        $this->send();
        $this->send(['code' => $this->emailedCode()])->assertSessionHas('success');
        $approval = Approval::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(Approval::AWAITING, $approval->status);
        $this->assertSame(5000000, $this->from->fresh()->available_balance);

        // Not the person who asked.
        $this->post(route('injongepay.approvals.approve', $approval), ['pin' => '48213'])->assertSessionHas('error');

        $this->flushSession();
        $this->actingAs($approver, 'staff')->post(route('injongepay.approvals.approve', $approval), ['pin' => '90817'])->assertSessionHas('success');
        $this->assertSame(Approval::EXECUTED, $approval->fresh()->status);
        $this->assertSame(1000000, $this->to->fresh()->available_balance);

        // A second click runs nothing again.
        $this->post(route('injongepay.approvals.approve', $approval), ['pin' => '90817'])->assertSessionHas('error');
        $this->assertSame(1, Transfer::query()->withoutGlobalScopes()->count());
    }

    public function test_platform_limits_are_never_exceeded(): void
    {
        $this->profile(overrides: ['limit_daily' => 1500000]);
        $this->asOwner();
        $this->send();
        $this->send(['code' => $this->emailedCode()])->assertSessionHas('success');

        $this->send(['amount' => '6000'])->assertSessionHas('error', fn ($m) => str_contains($m, 'limit'));
        $this->assertSame(1, Transfer::query()->withoutGlobalScopes()->count());
    }

    public function test_cash_out_accounts_need_gtbank_the_same_name_and_a_second_platform_person(): void
    {
        $this->fakeBank(['/disbursement/lookup' => Http::sequence()
            ->push(['names' => 'Rose Mary MUSABYIMANA', 'account' => '0788811607', 'msisdn' => '0788811607', 'email' => '', 'bank' => ['code' => '078', 'name' => 'MTN']])
            ->push(['names' => 'SOMEONE ELSE', 'account' => '0788000000', 'bank' => ['code' => '078', 'name' => 'MTN']])]);
        $accounts = app(SettlementAccounts::class);
        $this->asOperator();

        $account = $accounts->add($this->business, ['kind' => 'wallet', 'account' => '+250788811607', 'holder_name' => 'MUSABYIMANA Rose Mary']);
        $this->assertSame('0788811607', $account->account);
        $this->assertSame(SettlementAccount::VERIFIED, $account->status);

        try {
            $accounts->add($this->business, ['kind' => 'wallet', 'account' => '0788000000', 'holder_name' => 'Rose Mary']);
            $this->fail('Name mismatch accepted');
        } catch (InjongePayException) {
        }

        try {
            $accounts->approve($account, $this->operatorAdmin);
            $this->fail('Approved by the person who added it');
        } catch (InjongePayException) {
        }

        $second = $this->makeMember($this->operator, ['approve injongepay_platform.business']);
        $accounts->approve($account, $second);
        $this->assertSame(SettlementAccount::APPROVED, $account->fresh()->status);
        $this->assertFalse($account->fresh()->isUsable());
        $this->travel(25)->hours();
        $this->assertTrue($account->fresh()->isUsable());
    }

    public function test_business_admins_cannot_add_cash_out_accounts(): void
    {
        $this->asOwner();

        $this->get(route('injongepay.platform.settlement-accounts.index'))->assertForbidden();
        $this->withConfirmedPassword()->post(route('injongepay.platform.settlement-accounts.store'), ['business' => $this->business->uuid, 'kind' => 'bank', 'account' => '1', 'holder_name' => 'x'])->assertForbidden();
    }

    public function test_screens_render(): void
    {
        $this->asOwner();
        $this->get(route('injongepay.transfers.index'))->assertOk()->assertSee('name="pin"', false);
        $this->get(route('injongepay.approvals.index'))->assertOk();
        $this->get(route('injongepay.security'))->assertOk();
        $this->flushSession();
        $this->asOperator()->get(route('injongepay.platform.settlement-accounts.index'))->assertOk();
        $this->get(route('injongepay.platform.businesses.edit', $this->business))->assertOk()->assertSee('name="limit_daily"', false);
    }
}
