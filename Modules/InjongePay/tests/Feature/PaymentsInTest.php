<?php

namespace Modules\InjongePay\Tests\Feature;

use App\Models\Notification\Notification;
use Modules\InjongePay\Models\BankCall;
use Modules\InjongePay\Models\FeeAccrual;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\UnallocatedPayment;
use Modules\InjongePay\Models\WebhookEvent;
use Modules\InjongePay\Services\FeeSweep;
use Modules\InjongePay\Services\InternalAccounts;
use Modules\InjongePay\Services\PaymentIntake;
use Modules\InjongePay\Services\WebhookInbox;
use Modules\InjongePay\Support\PlatformSettings;

class PaymentsInTest extends InjongePayTestCase
{
    private const SECRET = 'webhook-secret-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSettings::setWebhookSecret(self::SECRET);
        $this->fakeBank();
    }

    private function hook(array $overrides = [], ?string $secret = self::SECRET)
    {
        return $this->postJson('/api/payments/webhook', $overrides + [
            'transactionId' => 'MPay_29545023174', 'merchantId' => '55500001', 'amount' => 10000.00, 'valueDate' => '2026-11-03',
            'status' => 'SUCCESS', 'participantName' => 'INHILLS', 'initiatingPartyName' => 'MTN', 'initiatingPartyId' => '078',
            'debtorName' => 'JOHN DOE', 'debtorAccountId' => '250788111222', 'creditorName' => 'Rhenium Group', 'creditorAccountId' => '55500001',
        ], $secret === null ? [] : ['X-GTBank-Webhook-Secret' => $secret]);
    }

    public function test_a_payment_is_credited_as_pending_with_the_same_response_as_production(): void
    {
        $wallet = $this->wallet();

        $this->hook()->assertStatus(201)->assertExactJson([
            'success' => true,
            'message' => 'Payment received.',
            'data' => [
                'reference' => Payment::query()->withoutGlobalScopes()->value('reference'),
                'wallet_merchant_code' => '55500001',
                'gross_amount' => 10000.0, 'fee_amount' => 200.0, 'net_amount' => 9800.0,
                'status' => 'AVAILABLE',
            ],
        ]);

        $wallet->refresh();
        $this->assertSame([980000, 0], [$wallet->pending_balance, $wallet->available_balance]);
        $payment = Payment::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertNull($payment->cleared_at);
        $this->assertSame('JOHN DOE', $payment->buyer_name);
        $this->assertSame(20000, (int) FeeAccrual::query()->sum('amount'));
        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertTrue(BankCall::query()->where('direction', 'inbound')->where('reference', 'MPay_29545023174')->exists());
    }

    public function test_the_same_transaction_twice_is_credited_once(): void
    {
        $wallet = $this->wallet();
        $first = $this->hook()->json('data.reference');

        $this->hook()->assertOk()->assertJsonPath('message', 'Payment already received.')->assertJsonPath('data.reference', $first);

        $this->assertSame(1, Payment::query()->withoutGlobalScopes()->count());
        $this->assertSame(980000, $wallet->fresh()->pending_balance);
        $this->assertSame(2, WebhookEvent::query()->value('deliveries'));
    }

    public function test_requests_without_the_secret_are_refused(): void
    {
        $this->wallet();

        $this->hook(secret: null)->assertStatus(401)->assertExactJson(['success' => false, 'message' => 'Unauthorized', 'errors' => null]);
        $this->hook(secret: 'wrong')->assertStatus(401);
        $this->assertSame(0, Payment::query()->withoutGlobalScopes()->count());
    }

    public function test_validation_errors_keep_the_production_shape(): void
    {
        $this->hook(['amount' => null])->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('message', 'Validation failed.')->assertJsonStructure(['errors' => ['amount']]);
    }

    public function test_non_success_notifications_credit_nothing(): void
    {
        $this->wallet();

        $this->hook(['status' => 'PENDING'])->assertOk()->assertExactJson(['success' => true, 'message' => 'Acknowledged.', 'data' => null]);
        $this->assertSame(0, Payment::query()->withoutGlobalScopes()->count());
    }

    public function test_money_for_a_code_that_cannot_take_it_is_held_and_the_platform_told(): void
    {
        $this->wallet(overrides: ['is_enabled' => false]);

        $this->hook()->assertOk()->assertJsonPath('message', 'Payment received and held for review.');
        $this->hook(['transactionId' => 'TX-UNKNOWN', 'merchantId' => '55599999'])->assertOk();

        $this->assertSame(0, Payment::query()->withoutGlobalScopes()->count());
        $this->assertEqualsCanonicalizing(['wallet_disabled', 'unknown_code'], UnallocatedPayment::query()->pluck('reason')->all());
        $this->assertTrue(Notification::query()->withoutGlobalScopes()->where('user_id', $this->operatorAdmin->id)->exists());
    }

    public function test_held_money_is_credited_once_to_the_chosen_wallet(): void
    {
        $this->hook(['merchantId' => '55599999'])->assertOk();
        $wallet = $this->wallet();
        $row = UnallocatedPayment::query()->firstOrFail();
        $this->asOperator();

        $this->withConfirmedPassword()->post(route('injongepay.platform.unallocated.allocate', $row), ['wallet' => $wallet->uuid, 'note' => 'Payer used an old code'])->assertSessionHasNoErrors();
        $this->withConfirmedPassword()->post(route('injongepay.platform.unallocated.allocate', $row), ['wallet' => $wallet->uuid, 'note' => 'again'])->assertSessionHas('error');

        $this->assertSame(UnallocatedPayment::ALLOCATED, $row->fresh()->status);
        $this->assertSame(1, Payment::query()->withoutGlobalScopes()->where('received_via', Payment::VIA_ALLOCATED)->count());
        $this->assertSame(980000, $wallet->fresh()->balance());
    }

    public function test_confirmation_from_gtbank_makes_money_available_and_releases_the_fees(): void
    {
        $wallet = $this->wallet();
        $this->hook();
        $payment = Payment::query()->withoutGlobalScopes()->firstOrFail();

        $this->assertSame(0, app(FeeSweep::class)->run());

        app(PaymentIntake::class)->clear($payment);
        app(PaymentIntake::class)->clear($payment->fresh());

        $this->assertSame([0, 980000], [$wallet->fresh()->pending_balance, $wallet->fresh()->available_balance]);
        $this->assertSame(20000, app(FeeSweep::class)->run());
        $this->assertSame(0, app(FeeSweep::class)->run());
        $this->assertSame(20000, app(InternalAccounts::class)->platformRevenue()->available_balance);
    }

    public function test_a_payment_from_gtbanks_own_list_is_available_at_once(): void
    {
        $wallet = $this->wallet();

        app(PaymentIntake::class)->receive(['transactionId' => 'TX-PULL', 'merchantId' => $wallet->merchant_code, 'amount' => '5000.00', 'status' => 'SUCCESS'], Payment::VIA_BANK_PULL);

        $this->assertSame([0, 490000], [$wallet->fresh()->pending_balance, $wallet->fresh()->available_balance]);
    }

    public function test_without_gtbank_payments_are_available_at_once(): void
    {
        config(['injongepay.gtbank.base_url' => null]);
        $wallet = $this->wallet();

        $this->hook()->assertCreated();

        $this->assertSame(980000, $wallet->fresh()->available_balance);
    }

    public function test_a_stored_webhook_that_failed_is_processed_by_the_scheduler(): void
    {
        $wallet = $this->wallet();
        app(WebhookInbox::class)->store('payment', 'TX-LATE:SUCCESS', ['transactionId' => 'TX-LATE', 'merchantId' => $wallet->merchant_code, 'amount' => 1000], '1.2.3.4');
        WebhookEvent::query()->update(['created_at' => now()->subMinutes(5)]);

        $this->artisan('injongepay:process-inbox')->assertSuccessful();

        $this->assertNotNull(WebhookEvent::query()->value('processed_at'));
        $this->assertSame(98000, $wallet->fresh()->pending_balance);
    }

    public function test_payments_screen_shows_only_the_business_own_payments(): void
    {
        [$other] = $this->makeBusiness(['business_name' => 'Other']);
        $this->wallet();
        $otherWallet = $this->wallet($other, ['name' => 'Other till']);
        $this->hook(['debtorName' => 'MINE']);
        $this->hook(['transactionId' => 'TX-OTHER', 'merchantId' => $otherWallet->merchant_code, 'debtorName' => 'NOT MINE']);
        $theirs = Payment::query()->withoutGlobalScopes()->where('bank_reference', 'TX-OTHER')->firstOrFail();

        $this->asOwner()->get(route('injongepay.payments.index'))->assertOk()->assertSee('MINE')->assertDontSee('NOT MINE');
        $this->get(route('injongepay.payments.show', $theirs))->assertNotFound();
        $this->get(route('injongepay.platform.unallocated.index'))->assertForbidden();
    }
}
