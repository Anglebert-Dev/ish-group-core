<?php

namespace Modules\InjongePay\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\InjongePay\Gtbank\BankPayloads;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\BankCall;

class GtbankClientTest extends InjongePayTestCase
{
    private function client(): GtbankClient
    {
        return app(GtbankClient::class);
    }

    public function test_token_is_fetched_once_cached_and_sent_with_the_participant_header(): void
    {
        $this->fakeBank(['/merchants/lookup' => Http::response(['responseCode' => '00', 'data' => ['merchant' => ['merchantId' => '55500001', 'status' => 'ACTIVE']]])]);

        $this->assertTrue($this->client()->lookupMerchant('55500001')->ok());
        $this->assertTrue($this->client()->lookupMerchant('55500001')->ok());

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/merchants/lookup') && $r->hasHeader('Authorization', 'Bearer tok-1') && $r->hasHeader('X-Participant-Name', 'INHILLS'));
        $this->assertSame('tok-1', Cache::get(GtbankClient::TOKEN_CACHE_KEY));
    }

    public function test_an_expired_token_is_refreshed_and_the_call_retried_once(): void
    {
        $this->fakeBank(['/merchants/lookup' => Http::sequence()
            ->push(['responseCode' => '09', 'responseMessage' => 'Expired authentication token'], 200)
            ->push(['responseCode' => '00', 'data' => ['merchant' => ['merchantId' => '55500001', 'status' => 'ACTIVE']]])]);

        $this->assertTrue($this->client()->lookupMerchant('55500001')->ok());
        Http::assertSentCount(4); // token, lookup (09), token again, lookup
    }

    public function test_outcomes_follow_http_status_and_gtbank_codes_not_message_text(): void
    {
        $this->fakeBank([
            'POST /disbursement/transfer' => null,
            '/disbursement/transfer' => Http::sequence()
                ->push(['error' => 'Request Validation failed, please try again later or contact the system admin for further support'], 409)
                ->push(['error' => 'Request Validation failed'], 400)
                ->push(['responseCode' => '10', 'responseMessage' => 'Insufficient funds'], 200)
                ->push(['responseCode' => '12', 'responseMessage' => 'Payment processing timeout'], 200)
                ->push('Bad gateway', 502),
        ]);
        $payload = ['reference' => 'CSH20261103000001', 'beneficiaryAccount' => '100123', 'amount' => 500];

        $duplicate = $this->client()->disbursementTransfer($payload);
        $this->assertTrue($duplicate->duplicate());
        $invalid = $this->client()->disbursementTransfer($payload);
        $this->assertTrue($invalid->rejected());
        $this->assertFalse($invalid->duplicate());
        $this->assertTrue($this->client()->disbursementTransfer($payload)->rejected());
        $this->assertTrue($this->client()->disbursementTransfer($payload)->unknown());
        $this->assertTrue($this->client()->disbursementTransfer($payload)->unknown());
    }

    public function test_a_post_that_times_out_is_unknown_and_never_resent(): void
    {
        $attempts = 0;
        $this->fakeBank(['/disbursement/transfer' => function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('cURL error 28: timed out');
        }]);

        $result = $this->client()->disbursementTransfer(['reference' => 'CSH20261103000001', 'beneficiaryAccount' => '1', 'amount' => 1]);

        $this->assertTrue($result->unknown());
        $this->assertSame(1, $attempts);
    }

    public function test_reads_are_retried_once_after_a_network_error(): void
    {
        $calls = 0;
        $this->fakeBank(['/disbursement/status' => function () use (&$calls) {
            if (++$calls === 1) {
                throw new ConnectionException('reset');
            }

            return Http::response(['status' => 'ACSC', 'request_id' => 'CSH1', 'cbs_transaction_reference' => 'S1']);
        }]);

        $result = $this->client()->disbursementStatus('CSH1');

        $this->assertTrue($result->ok());
        $this->assertSame(['status' => 'ACSC', 'reference' => 'CSH1', 'cbs_reference' => 'S1', 'amount' => null, 'account' => null, 'receiver' => null, 'completed_at' => null], BankPayloads::disbursement($result->data));
    }

    public function test_not_found_answers_are_recognised(): void
    {
        $this->fakeBank([
            '/disbursement/status' => Http::response(['error' => 'Transaction not found for the provided reference']),
            '/merchants/lookup' => Http::response(['responseCode' => '01', 'responseMessage' => 'Merchant not found']),
        ]);

        $this->assertTrue($this->client()->disbursementStatus('X')->notFound());
        $this->assertTrue($this->client()->lookupMerchant('55500009')->notFound());
    }

    public function test_every_call_is_logged_without_secrets(): void
    {
        $this->fakeBank(['/merchants/lookup' => Http::response(['responseCode' => '00', 'data' => []])]);
        $this->client()->lookupMerchant('55500001');

        $token = BankCall::query()->where('endpoint', '/auth/token')->firstOrFail();
        $this->assertStringNotContainsString('secret-value', json_encode($token->request));
        $this->assertSame('[REDACTED]', $token->response['access_token']);
        $this->assertSame('55500001', BankCall::query()->where('endpoint', '/merchants/lookup')->value('reference'));
    }

    public function test_lookup_shapes_seen_in_the_wild_are_all_read(): void
    {
        $this->assertSame(['merchant_id' => '55500006', 'status' => 'ACTIVE', 'name' => 'Capital Petroleum Ltd', 'owner' => 'Owner A'],
            BankPayloads::merchant(['responseCode' => '00', 'data' => ['participantName' => 'GTBANK', 'merchant' => ['merchantId' => '55500006', 'businessName' => 'Capital Petroleum Ltd', 'businessOwnerName' => 'Owner A', 'status' => 'ACTIVE']]]));
        $this->assertSame('Owner B', BankPayloads::merchant(['data' => ['merchant' => ['businessOwner' => 'Owner B']]])['owner']);
        $this->assertSame('INACTIVE', BankPayloads::merchant(['responseCode' => '01', 'data' => ['merchantId' => '55500001', 'status' => 'inactive']])['status']);
    }
}
