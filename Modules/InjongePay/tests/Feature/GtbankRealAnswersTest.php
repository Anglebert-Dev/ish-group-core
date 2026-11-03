<?php

namespace Modules\InjongePay\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\InjongePay\Gtbank\BankPayloads;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Gtbank\JavaError;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Support\PlatformSettings;

/**
 * GTBank's real answers (tests/fixtures/gtbank-production.php), not its documentation.
 */
class GtbankRealAnswersTest extends InjongePayTestCase
{
    private array $real;

    protected function setUp(): void
    {
        parent::setUp();
        $this->real = require __DIR__.'/../fixtures/gtbank-production.php';
    }

    private function client(): GtbankClient
    {
        return app(GtbankClient::class);
    }

    public function test_a_status_check_wrapped_in_a_500_is_read_as_not_found(): void
    {
        $this->fakeBank(['/disbursement/status' => Http::response($this->real['status_not_found'], 500)]);

        $result = $this->client()->disbursementStatus('CSH-20261103194452-6804');

        $this->assertTrue($result->notFound());
        $this->assertSame(400, $result->downstreamStatus);
        $this->assertStringContainsString('no such transaction', $result->message());
    }

    public function test_digipay_refusing_gtbanks_login_is_not_taken_as_a_failed_transfer(): void
    {
        $this->fakeBank(['/disbursement/transfer' => Http::response($this->real['transfer_downstream_401'], 500)]);

        $result = $this->client()->disbursementTransfer(['reference' => 'CSH20261103000001', 'beneficiaryAccount' => '0788811607', 'amount' => 1000]);

        // Unknown: only a status check may say it didn't happen.
        $this->assertTrue($result->unknown());
        $this->assertSame(401, $result->downstreamStatus);
        $this->assertStringContainsString('tell GTBank', $result->message());
    }

    public function test_the_status_after_a_timeout_is_read_in_full(): void
    {
        $this->fakeBank(['/disbursement/status' => Http::response($this->real['status_acsc_wallet'])]);

        $result = $this->client()->disbursementStatus('CSH-20261103202003-5202');

        $this->assertTrue($result->ok());
        $this->assertSame([
            'status' => 'ACSC', 'reference' => 'CSH-20261103202003-5202', 'cbs_reference' => 'M00026711', 'amount' => null,
            'account' => '0788811607', 'receiver' => 'Rose Mary MUSABYIMANA', 'completed_at' => '2026-11-03T18:20:04.253982',
        ], BankPayloads::disbursement($result->data));
    }

    public function test_an_unreadable_lookup_means_the_account_is_not_verified(): void
    {
        $this->fakeBank(['/disbursement/lookup' => Http::response($this->real['lookup_unreadable'], 500)]);

        $result = $this->client()->disbursementLookup('572308');

        $this->assertTrue($result->rejected());
        $this->assertSame('UNREADABLE', $result->code);
    }

    public function test_wallet_numbers_go_to_gtbank_as_07_numbers(): void
    {
        $this->fakeBank([
            '/disbursement/lookup' => Http::response($this->real['lookup_wallet']),
            '/disbursement/transfer' => Http::response(['identifier' => 'CSH1', 'status' => 'PENDING']),
        ]);

        $this->assertTrue($this->client()->disbursementLookup('+250 788 811 607')->ok());
        $this->client()->disbursementTransfer(['reference' => 'CSH1', 'beneficiaryAccount' => '250788811607', 'amount' => 500]);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/disbursement/lookup') && $r->data()['identifier'] === '0788811607');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/disbursement/transfer') && $r['beneficiaryAccount'] === '0788811607');
        $this->assertSame('01705070002', GtbankClient::beneficiary('01705070002'));
    }

    public function test_account_lookups_and_merchant_answers_parse(): void
    {
        $this->assertSame(['names' => 'BAGARAGAZA MAURICE', 'account' => '01705070002', 'bank_code' => '076', 'bank_name' => null], BankPayloads::account($this->real['lookup_bank_account']));
        $this->assertSame('ACTIVE', BankPayloads::merchant($this->real['merchant_lookup_active'])['status']);

        $this->fakeBank(['/merchants/update' => Http::response($this->real['merchant_update_pending'])]);
        $this->assertTrue($this->client()->updateMerchant(['merchantId' => '55500004'])->ok());
    }

    public function test_java_error_shapes_are_recognised(): void
    {
        $this->assertSame(JavaError::SPRING_DEFAULT, JavaError::parse(['timestamp' => '2026-11-03T17:45:37', 'status' => 500, 'error' => 'Internal Server Error', 'path' => '/prodpaymentbridge/disbursement/transfer'])->kind);
        $this->assertSame(JavaError::IO, JavaError::parse(['error' => 'Disbursement initiation failed: I/O error on POST request for "https://api.gtbank.rw/x": Read timed out'])->kind);
        $this->assertSame(JavaError::UNREADABLE, JavaError::parse($this->real['lookup_unreadable'])->kind);

        $wrapped = JavaError::parse($this->real['status_not_found']);
        $this->assertSame(JavaError::DOWNSTREAM_HTTP, $wrapped->kind);
        $this->assertSame('https://api.gtbank.rw/digipay/api/v2/digipay/check-status', $wrapped->downstreamUrl);
        $this->assertSame('FAILED', $wrapped->downstreamBody['status']);
        $this->assertTrue($wrapped->saysNotFound());
    }

    public function test_webhooks_from_gtbanks_address_without_a_secret_are_accepted(): void
    {
        $this->fakeBank();
        PlatformSettings::setWebhookSecret('s3cret');
        PlatformSettings::set('webhook_allowed_ips', $this->real['webhook_ip']);
        $wallet = $this->wallet();
        $body = ['transactionId' => '31350756038', 'merchantId' => $wallet->merchant_code, 'amount' => 200, 'status' => 'SUCCESS'];

        $this->withServerVariables(['REMOTE_ADDR' => '10.22.198.46'])->postJson('/api/payments/webhook', $body)->assertCreated();
        $this->withServerVariables(['REMOTE_ADDR' => '41.186.1.1'])->postJson('/api/payments/webhook', $body + ['transactionId' => 'X2'])->assertUnauthorized();
        // A wrong secret is refused even from GTBank's address.
        $this->withServerVariables(['REMOTE_ADDR' => '10.22.198.46'])->postJson('/api/payments/webhook', $body + ['transactionId' => 'X3'], ['X-GTBank-Webhook-Secret' => 'wrong'])->assertUnauthorized();

        $this->assertSame(1, Payment::query()->withoutGlobalScopes()->count());
        // Still only pending: the bank's own list has to confirm it.
        $this->assertSame(0, $wallet->fresh()->available_balance);
    }

    public function test_operator_sets_gtbanks_addresses(): void
    {
        $this->asOperator()->withConfirmedPassword()->put(route('injongepay.platform.settings.ips'), ['webhook_allowed_ips' => '10.22.198.46, 10.22.0.0/16'])->assertSessionHasNoErrors();
        $this->assertSame(['10.22.198.46', '10.22.0.0/16'], PlatformSettings::webhookAllowedIps());

        $this->withConfirmedPassword()->put(route('injongepay.platform.settings.ips'), ['webhook_allowed_ips' => 'gtbank'])->assertSessionHasErrors('webhook_allowed_ips');
    }
}
