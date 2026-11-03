<?php

namespace Modules\InjongePayConnect\Tests\Feature;

use App\Services\Module\ModuleManager;
use Illuminate\Support\Carbon;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\PaymentIntake;
use Modules\InjongePay\Services\PaymentReversal;
use Modules\InjongePay\Tests\Feature\InjongePayTestCase;
use Modules\InjongePayConnect\Models\Connection;
use Modules\InjongePayConnect\Models\Integrator;
use Modules\InjongePayConnect\Models\Token;
use Modules\InjongePayConnect\Services\Credentials;

/**
 * /api/partner/v1 answers exactly as the production app did (shapes, codes,
 * messages), plus the fixes that keep the contract.
 */
class PartnerApiV1Test extends InjongePayTestCase
{
    private Wallet $till;

    private Connection $connection;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        app(ModuleManager::class)->install('InjongePayConnect');
        $this->profile(overrides: ['tin' => '123456789']);
        $this->till = $this->wallet(overrides: ['name' => 'Main Till']);
        $integrator = Integrator::query()->create(['name' => 'Injonge EBM', 'profile' => 'pos-ebm-v1', 'is_enabled' => true]);
        [$this->connection, $this->secret] = app(Credentials::class)->create($integrator, $this->business);
    }

    private function pay(string $id, string $amount = '5000.00', string $name = 'JOHN DOE'): Payment
    {
        return app(PaymentIntake::class)->receive(['transactionId' => $id, 'merchantId' => $this->till->merchant_code, 'amount' => $amount, 'status' => 'SUCCESS',
            'initiatingPartyName' => 'MTN', 'debtorName' => $name, 'debtorAccountId' => '250788111222'], Payment::VIA_BANK_PULL)[1];
    }

    private function token(): string
    {
        return $this->post('/api/partner/v1/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $this->connection->client_id, 'client_secret' => $this->secret])->json('access_token');
    }

    private function api(): static
    {
        return $this->withToken($this->token());
    }

    public function test_token_exchange_and_its_errors(): void
    {
        $this->post('/api/partner/v1/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $this->connection->client_id, 'client_secret' => $this->secret])
            ->assertOk()->assertJsonStructure(['token_type', 'access_token', 'expires_in'])->assertJson(['token_type' => 'Bearer', 'expires_in' => 3600]);

        $this->post('/api/partner/v1/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $this->connection->client_id, 'client_secret' => 'wrong'])
            ->assertStatus(401)->assertExactJson(['error' => ['code' => 'invalid_client', 'message' => 'Invalid client credentials.']]);

        $this->post('/api/partner/v1/oauth/token', ['client_id' => 'x'])->assertStatus(400)->assertJsonPath('error.code', 'invalid_request');

        $this->getJson('/api/partner/v1/institution')->assertStatus(401)->assertExactJson(['error' => ['code' => 'unauthorized', 'message' => 'Unauthorized action']]);
        $this->withToken('garbage')->getJson('/api/partner/v1/institution')->assertStatus(401);
    }

    public function test_institution_and_merchant_accounts(): void
    {
        $this->api()->getJson('/api/partner/v1/institution')->assertExactJson([
            'id' => $this->business->uuid, 'name' => 'Rhenium Group', 'tin' => '123456789', 'status' => 'ACTIVE',
        ]);

        $this->getJson('/api/partner/v1/merchant-accounts')->assertExactJson(['data' => [[
            'id' => $this->till->uuid, 'merchant_code' => $this->till->merchant_code, 'name' => 'Main Till', 'provider' => 'GTBANK', 'status' => 'ACTIVE',
        ]]]);
    }

    public function test_payment_shape_and_list(): void
    {
        Carbon::setTestNow('2026-11-03 10:15:30');
        $payment = $this->pay('MPay_1');

        $this->api()->getJson('/api/partner/v1/payments/'.$payment->uuid)->assertExactJson([
            'id' => $payment->uuid, 'amount' => 5000.0, 'fee_amount' => 100.0, 'currency' => 'RWF', 'network' => 'MTN',
            'payer_name' => 'JOHN DOE', 'payer_phone' => '250788111222', 'provider_reference' => 'MPay_1',
            'received_at' => '2026-11-03T10:15:30+02:00', 'status' => 'AVAILABLE',
            'invoice_no' => null, 'is_partial' => null, 'note' => null, 'invoice_total' => null, 'balance_after' => null,
            'confirmed_by_name' => null, 'confirmed_at' => null,
        ]);

        $this->getJson('/api/partner/v1/merchant-accounts/'.$this->till->uuid.'/payments?amount=5000&status=AVAILABLE')
            ->assertOk()->assertJson(['page' => 1, 'per_page' => 50, 'total' => 1])->assertJsonPath('data.0.id', $payment->uuid);
        $this->getJson('/api/partner/v1/merchant-accounts/'.$this->till->uuid.'/payments?amount=4999')->assertJson(['total' => 0]);
    }

    public function test_confirm_is_idempotent_and_conflicts_say_which_invoice(): void
    {
        $payment = $this->pay('MPay_1');
        $body = ['invoice_no' => 'INV-42', 'partial' => true, 'invoice_total' => 8000, 'balance_after' => 3000, 'note' => 'part 1', 'cashier' => ['id' => '7', 'name' => 'Aline']];
        $this->api();

        $this->postJson('/api/partner/v1/payments/'.$payment->uuid.'/confirm', $body, ['Idempotency-Key' => 'pos:1:INV-42:'.$payment->uuid])
            ->assertOk()->assertJson(['status' => 'CONFIRMED', 'invoice_no' => 'INV-42', 'is_partial' => true, 'invoice_total' => 8000.0, 'balance_after' => 3000.0, 'confirmed_by_name' => 'Aline']);

        // Same key and invoice: the same answer, not an error.
        $this->postJson('/api/partner/v1/payments/'.$payment->uuid.'/confirm', $body, ['Idempotency-Key' => 'pos:1:INV-42:'.$payment->uuid])->assertOk();

        $this->postJson('/api/partner/v1/payments/'.$payment->uuid.'/confirm', ['invoice_no' => 'INV-99'])
            ->assertStatus(409)->assertExactJson(['error' => ['code' => 'ALREADY_CONFIRMED', 'message' => 'This payment has already been confirmed.', 'invoice_no' => 'INV-42']]);

        $this->getJson('/api/partner/v1/invoices/INV-42/payments')->assertOk()->assertJsonPath('data.0.payment_id', $payment->uuid)->assertJsonPath('data.0.amount', 5000);

        $this->postJson('/api/partner/v1/payments/'.$payment->uuid.'/unconfirm', ['reason' => 'Deleted in POS'])->assertOk()->assertJsonPath('status', 'AVAILABLE');
        $this->postJson('/api/partner/v1/payments/'.$payment->uuid.'/unconfirm', ['reason' => 'again'])
            ->assertStatus(409)->assertExactJson(['error' => ['code' => 'conflict', 'message' => 'This payment has not been confirmed.']]);
        $this->getJson('/api/partner/v1/invoices/INV-42/payments')->assertJsonPath('data.0.status', 'UNCONFIRMED');
    }

    public function test_fixes_that_keep_the_contract(): void
    {
        $cashedOut = $this->pay('MPay_1');
        $reversed = $this->pay('MPay_2', '3000.00');
        Payment::query()->withoutGlobalScopes()->whereKey($cashedOut->id)->update(['payment_status' => Payment::STATUS_CASHED_OUT]);
        app(PaymentReversal::class)->reverse($reversed, 'test');
        $this->api();

        // A cashed-out payment is still AVAILABLE to the POS.
        $this->getJson('/api/partner/v1/merchant-accounts/'.$this->till->uuid.'/payments?status=AVAILABLE')->assertJson(['total' => 1])->assertJsonPath('data.0.id', $cashedOut->uuid);
        $this->getJson('/api/partner/v1/merchant-accounts/'.$this->till->uuid.'/payments?status=REVERSED')->assertJsonPath('data.0.status', 'REVERSED');

        // A reversed payment can't be confirmed.
        $this->postJson('/api/partner/v1/payments/'.$reversed->uuid.'/confirm', ['invoice_no' => 'INV-1'])->assertStatus(409)->assertJsonPath('error.code', 'conflict');

        // Confirming shows up in updated_since.
        $since = now()->addSecond()->toIso8601String();
        $this->travel(5)->seconds();
        $this->postJson('/api/partner/v1/payments/'.$cashedOut->uuid.'/confirm', ['invoice_no' => 'INV-2'])->assertOk();
        $this->getJson('/api/partner/v1/merchant-accounts/'.$this->till->uuid.'/payments?updated_since='.urlencode($since))->assertJson(['total' => 1]);

        // date_to as a date includes that day.
        $this->getJson('/api/partner/v1/merchant-accounts/'.$this->till->uuid.'/payments?date_from='.now()->toDateString().'&date_to='.now()->toDateString())->assertJson(['total' => 2]);
    }

    public function test_another_business_ids_are_not_found(): void
    {
        [$other] = $this->makeBusiness(['business_name' => 'Other']);
        $theirs = $this->wallet($other);

        $this->api()->getJson('/api/partner/v1/merchant-accounts/'.$theirs->uuid.'/payments')->assertStatus(404)->assertExactJson(['error' => ['code' => 'not_found', 'message' => 'Wallet not found']]);
        $this->getJson('/api/partner/v1/payments/00000000-0000-0000-0000-000000000000')->assertStatus(404)->assertJsonPath('error.message', 'Payment not found');
    }

    public function test_totals_for_a_day(): void
    {
        $this->pay('MPay_1', '5000.00');
        $this->pay('MPay_2', '2000.00');

        $this->api()->getJson('/api/partner/v1/merchant-accounts/'.$this->till->uuid.'/totals?date='.now()->toDateString())
            ->assertExactJson(['date' => now()->toDateString(), 'count' => 2, 'gross_total' => 7000.0, 'fee_total' => 140.0, 'net_total' => 6860.0]);
        $this->getJson('/api/partner/v1/merchant-accounts/'.$this->till->uuid.'/totals')->assertStatus(422)->assertJsonPath('error.code', 'validation_error');
    }

    public function test_rotating_or_switching_off_ends_tokens_at_once(): void
    {
        $token = $this->token();
        $this->withToken($token)->getJson('/api/partner/v1/institution')->assertOk();

        app(Credentials::class)->setEnabled($this->connection->fresh(), false);
        $this->withToken($token)->getJson('/api/partner/v1/institution')->assertStatus(401);
        $this->assertSame(0, Token::query()->count());
    }

    public function test_every_call_is_logged_without_secrets(): void
    {
        $this->token();

        $call = \Modules\InjongePayConnect\Models\ApiCall::query()->firstOrFail();
        $this->assertSame('[REDACTED]', $call->request['client_secret']);
        $this->assertSame('[REDACTED]', $call->response['access_token']);
    }

    public function test_legacy_ebm_lookup_is_unchanged(): void
    {
        config(['injongepayconnect.legacy_ebm_key' => 'ebm-key']);
        $payment = $this->pay('MPay_1');

        $this->getJson('/api/ebm/payments/lookup?bank_reference=MPay_1', ['X-EBM-Api-Key' => 'ebm-key'])->assertOk()
            ->assertJson(['success' => true, 'message' => 'Payment found.', 'data' => ['reference' => $payment->reference, 'bank_reference' => 'MPay_1', 'gross_amount' => 5000.0, 'bank_status' => 'SUCCESS']]);
        $this->getJson('/api/ebm/payments/lookup?bank_reference=MPay_1', ['X-EBM-Api-Key' => 'x'])->assertStatus(401)->assertExactJson(['success' => false, 'message' => 'Unauthorized', 'errors' => null]);
        $this->getJson('/api/ebm/payments/lookup?bank_reference=nope', ['X-EBM-Api-Key' => 'ebm-key'])->assertStatus(404)->assertJsonPath('message', 'No payment found for the given reference.');
    }

    public function test_screens_for_platform_and_business(): void
    {
        $this->asOperator()->get(route('injongepayconnect.platform.index'))->assertOk()->assertSee($this->connection->client_id);
        $this->withConfirmedPassword()->post(route('injongepayconnect.platform.connections.rotate', $this->connection->uuid))->assertSessionHas('injongepayconnect_secret');

        $this->flushSession();
        $this->asOwner()->get(route('injongepayconnect.connections'))->assertOk()->assertSee('Injonge EBM');
        $this->get(route('injongepayconnect.platform.index'))->assertForbidden();
    }
}
