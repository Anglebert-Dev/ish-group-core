<?php

namespace Modules\InjongePay\Tests\Feature;

use App\Models\Notification\Notification as InApp;
use App\Notifications\BrandedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\ReconciliationItem;
use Modules\InjongePay\Models\StatementCheck;
use Modules\InjongePay\Models\TransactionPin;
use Modules\InjongePay\Services\Ledger;
use Modules\InjongePay\Services\PaymentIntake;
use Modules\InjongePay\Services\Security\Pins;
use Modules\InjongePay\Services\SettlementAccounts;

class SecurityAndStatementTest extends InjongePayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function codeSentTo($user): string
    {
        $code = '';
        Notification::assertSentTo($user, BrandedNotification::class, function (BrandedNotification $n) use (&$code) {
            if (preg_match('/\b(\d{6})\b/', json_encode($n), $m)) {
                $code = $m[1];
            }

            return true;
        });

        return $code;
    }

    public function test_changing_a_pin_needs_a_code_and_the_admins_are_told(): void
    {
        $cashier = $this->makeMember($this->business, ['create injongepay.cashout']);
        $this->actingAs($cashier, 'staff');

        // First PIN: the password is enough.
        $this->withConfirmedPassword()->post(route('injongepay.security.pin'), ['pin' => '48213', 'pin_confirmation' => '48213'])->assertSessionHas('success');
        $this->assertTrue(InApp::query()->withoutGlobalScopes()->where('user_id', $this->owner->id)->where('title', __('injongepay::injongepay.alert_pin_set'))->exists());

        // Changing it: a code is emailed and must be given.
        $this->withConfirmedPassword()->post(route('injongepay.security.pin'), ['pin' => '90817', 'pin_confirmation' => '90817'])->assertSessionHas('pin_step_up', 'email');
        $this->withConfirmedPassword()->post(route('injongepay.security.pin'), ['pin' => '90817', 'pin_confirmation' => '90817', 'code' => '000000'])->assertSessionHas('error');
        $this->withConfirmedPassword()->post(route('injongepay.security.pin'), ['pin' => '90817', 'pin_confirmation' => '90817', 'code' => $this->codeSentTo($cashier)])->assertSessionHas('success');

        app(Pins::class)->check($cashier->fresh(), '90817');
        $this->assertTrue(InApp::query()->withoutGlobalScopes()->where('user_id', $this->owner->id)->where('title', __('injongepay::injongepay.alert_pin_changed'))->exists());
    }

    public function test_a_new_settlement_account_and_new_limits_are_told_to_the_admins(): void
    {
        $this->asOwner();
        app(SettlementAccounts::class)->add($this->business, ['kind' => 'wallet', 'account' => '0788811607', 'holder_name' => 'Rose Mary']);
        $this->assertTrue(InApp::query()->withoutGlobalScopes()->where('user_id', $this->owner->id)->where('title', __('injongepay::injongepay.alert_account_added'))->exists());

        $this->flushSession();
        $this->asOperator()->withConfirmedPassword()->put(route('injongepay.platform.businesses.update', $this->business), [
            'tin' => '100001498', 'country' => 'RW', 'contact_person' => 'A', 'contact_phone' => '+250788111222', 'contact_email' => 'a@example.com', 'limit_daily' => '500000',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(InApp::query()->withoutGlobalScopes()->where('user_id', $this->owner->id)->where('title', __('injongepay::injongepay.alert_limits'))->exists());
    }

    public function test_many_amounts_adding_up_within_an_hour_need_the_extra_code(): void
    {
        $this->profile(overrides: ['step_up_hourly_above' => 1500000]);
        $from = $this->wallet(overrides: ['name' => 'A']);
        $to = $this->wallet(overrides: ['name' => 'B']);
        app(Ledger::class)->post($from, LedgerEntry::CREDIT, LedgerEntry::AVAILABLE, 5000000, 'payment', 'seed');
        app(Pins::class)->set($this->owner, '48213');
        TransactionPin::query()->update(['set_at' => now()->subDays(3)]);
        $this->asOwner();
        $send = fn (string $amount, ?string $code = null) => $this->post(route('injongepay.transfers.store'), array_filter(['request_token' => (string) \Illuminate\Support\Str::uuid(), 'wallet' => $from->uuid, 'merchant_code' => $to->merchant_code, 'amount' => $amount, 'pin' => '48213', 'code' => $code]));

        // First: a new browser asks anyway; after that this browser is trusted.
        $send('10000');
        $cookie = $send('10000', $this->codeSentTo($this->owner))->getCookie(\Modules\InjongePay\Services\Security\Devices::COOKIE, false);
        $this->withUnencryptedCookie(\Modules\InjongePay\Services\Security\Devices::COOKIE, $cookie->getValue());

        // 10,000 + 4,000 is under 15,000: the PIN is enough.
        $send('4000')->assertSessionHas('success');
        // 14,000 + 2,000 goes over 15,000 in the hour: the extra code is asked.
        $send('2000')->assertSessionHas('step_up', fn ($s) => in_array('velocity_amount', $s['reasons'], true));
    }

    public function test_requests_waiting_more_than_a_day_expire_and_the_requester_is_told(): void
    {
        $approval = Approval::query()->create([
            'business_id' => $this->business->id, 'wallet_id' => $this->wallet()->id, 'operation' => 'transfer', 'amount' => 100000, 'payload' => [],
            'requested_by' => $this->owner->id, 'status' => Approval::AWAITING, 'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('injongepay:expire-approvals')->assertSuccessful();

        $this->assertSame(Approval::EXPIRED, $approval->fresh()->status);
        $this->assertTrue(InApp::query()->withoutGlobalScopes()->where('user_id', $this->owner->id)->where('title', __('injongepay::injongepay.notify_approval_expired'))->exists());
    }

    public function test_gtbank_statement_is_compared_with_the_books(): void
    {
        config(['injongepay.gtbank.base_url' => null]);
        $wallet = $this->wallet();
        foreach (['MPay_101' => '10000.00', 'MPay_102' => '5000.00'] as $ref => $amount) {
            app(PaymentIntake::class)->receive(['transactionId' => $ref, 'merchantId' => $wallet->merchant_code, 'amount' => $amount, 'status' => 'SUCCESS'], Payment::VIA_BANK_PULL);
        }
        $cashout = Cashout::query()->create(['reference' => 'CSH20261103000001', 'business_id' => $this->business->id, 'wallet_id' => $wallet->id, 'amount' => 500000,
            'account' => '0788811607', 'account_holder' => 'X', 'status' => Cashout::COMPLETED, 'completed_at' => now()]);
        app(Ledger::class)->post($wallet->fresh(), LedgerEntry::DEBIT, LedgerEntry::AVAILABLE, 500000, 'cashout', 'cashout:'.$cashout->id, ['source' => $cashout]);
        $day = now()->format('d/m/Y');
        $holds = app(\Modules\InjongePay\Services\StatementCheck::class)->booksHold();

        $csv = "GUARANTY TRUST BANK RWANDA\nStatement of account 2270038561\n\n"
            ."Transaction Date,Narration,Debit,Credit,Balance\n"
            ."{$day},MERCHANT PAY MPay_101 55500001,,\"10,000.00\",\"10,000.00\"\n"
            ."{$day},MERCHANT PAY MPay_102 55500001,,\"5,000.00\",\"15,000.00\"\n"
            ."{$day},DISBURSEMENT CSH20261103000001,\"5,000.00\",,\"".number_format($holds / 100, 2)."\"\n";
        $this->asOperator()->post(route('injongepay.platform.statements.store'), ['from' => now()->toDateString(), 'to' => now()->toDateString(), 'statement' => UploadedFile::fake()->createWithContent('gtbank.csv', $csv)])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(StatementCheck::query()->latest('id')->first()->agrees);

        // A credit GTBank has that we don't: differs, and a difference opens.
        $csv .= "{$day},MERCHANT PAY MPay_999 55500001,,700.00,\n";
        $this->post(route('injongepay.platform.statements.store'), ['from' => now()->toDateString(), 'to' => now()->toDateString(), 'statement' => UploadedFile::fake()->createWithContent('gtbank.csv', $csv)])->assertSessionHas('error');
        $check = StatementCheck::query()->latest('id')->first();
        $this->assertFalse($check->agrees);
        $this->assertCount(1, $check->unmatched['bank_credits']);
        $this->assertTrue(ReconciliationItem::query()->where('kind', 'statement_difference')->exists());
        $this->get(route('injongepay.platform.statements.show', $check))->assertOk()->assertSee('MPay_999');
        $this->get(route('injongepay.platform.statements.index'))->assertOk();

        $this->post(route('injongepay.platform.statements.store'), ['from' => now()->toDateString(), 'to' => now()->toDateString(), 'statement' => UploadedFile::fake()->createWithContent('x.csv', "a,b\n1,2\n")])->assertSessionHas('error');
    }
}
