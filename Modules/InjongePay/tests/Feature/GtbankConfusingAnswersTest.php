<?php

namespace Modules\InjongePay\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\InjongePay\Gtbank\BankAnswers;
use Modules\InjongePay\Gtbank\BankCodes;
use Modules\InjongePay\Gtbank\BankResult;
use Modules\InjongePay\Gtbank\GtbankClient;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every way a GTBank answer could be misread. The rule under test: a write is
 * "done" or "refused" only on a clear answer; everything else is "unknown",
 * and a disbursement's fate is decided only by a status about our reference,
 * amount and account.
 */
class GtbankConfusingAnswersTest extends InjongePayTestCase
{
    private const REF = 'CSH20261103000001';

    private const PAYLOAD = ['reference' => self::REF, 'beneficiaryAccount' => '0788811607', 'amount' => 1000];

    public static function transferAnswers(): array
    {
        $html = '<html><head><title>502 Bad Gateway</title></head><body>nginx</body></html>';

        return [
            'accepted' => [fn () => Http::response(['identifier' => self::REF, 'creditTime' => '2026-11-03T15:28:17Z', 'status' => 'PENDING']), BankResult::OK],
            'accepted, numeric code 0' => [fn () => Http::response(['responseCode' => 0, 'identifier' => self::REF, 'status' => 'PENDING']), BankResult::OK],
            'empty 200' => [fn () => Http::response('', 200), BankResult::UNKNOWN],
            'empty 202' => [fn () => Http::response('', 202), BankResult::UNKNOWN],
            'html proxy page 200' => [fn () => Http::response($html, 200, ['Content-Type' => 'text/html']), BankResult::UNKNOWN],
            'html 502' => [fn () => Http::response($html, 502), BankResult::UNKNOWN],
            '503' => [fn () => Http::response(['error' => 'Service Unavailable'], 503), BankResult::UNKNOWN],
            '504 gateway timeout' => [fn () => Http::response('', 504), BankResult::UNKNOWN],
            'truncated json' => [fn () => Http::response('{"identifier":"CSH2026110300', 200), BankResult::UNKNOWN],
            'json string inside quotes' => [fn () => Http::response('"{\"identifier\":\"'.self::REF.'\",\"status\":\"PENDING\"}"', 200), BankResult::OK],
            'bom before json' => [fn () => Http::response("\xEF\xBB\xBF".'{"identifier":"'.self::REF.'","status":"PENDING"}', 200), BankResult::OK],
            'redirect' => [fn () => Http::response('', 302, ['Location' => 'https://elsewhere.test']), BankResult::UNKNOWN],
            'too many requests' => [fn () => Http::response(['error' => 'Too Many Requests'], 429), BankResult::UNKNOWN],
            'spring default 500' => [fn () => Http::response(['timestamp' => '2026-11-03T17:45:37.104+00:00', 'status' => 500, 'error' => 'Internal Server Error', 'path' => '/prodpaymentbridge/disbursement/transfer'], 500), BankResult::UNKNOWN],
            'wrapped digipay 401' => [fn () => Http::response(['error' => 'Disbursement initiation failed: 401  on POST request for "https://api.gtbank.rw/digipay/api/v2/digipay/pay": [no body]'], 500), BankResult::UNKNOWN],
            'wrapped digipay 400' => [fn () => Http::response(['error' => 'Disbursement initiation failed: 400  on POST request for "https://api.gtbank.rw/digipay/api/v2/digipay/pay": "{\"error\":\"Invalid account\"}"'], 500), BankResult::UNKNOWN],
            'wrapped i/o timeout' => [fn () => Http::response(['error' => 'Disbursement initiation failed: I/O error on POST request for "https://api.gtbank.rw/x": Read timed out'], 500), BankResult::UNKNOWN],
            '200 with an error and nothing else' => [fn () => Http::response(['error' => 'Something went wrong']), BankResult::UNKNOWN],
            'code 96 internal error' => [fn () => Http::response(['responseCode' => '96', 'responseMessage' => 'Internal server error']), BankResult::UNKNOWN],
            'code 12 timeout' => [fn () => Http::response(['responseCode' => '12']), BankResult::UNKNOWN],
            'bridge validation 400' => [fn () => Http::response(['error' => 'Request Validation failed, please try again later or contact the system admin for further support'], 400), BankResult::REJECTED],
            'insufficient funds code 10' => [fn () => Http::response(['responseCode' => '10', 'responseMessage' => 'Insufficient funds']), BankResult::REJECTED],
            'duplicate 409 (an earlier try got through)' => [fn () => Http::response(['error' => 'Reference already exists'], 409), BankResult::REJECTED],
            'read timeout after sending' => [fn () => throw new ConnectionException('cURL error 28: Operation timed out after 60002 milliseconds with 0 bytes received'), BankResult::UNKNOWN],
            'connection reset mid answer' => [fn () => throw new ConnectionException('cURL error 56: Recv failure: Connection reset by peer'), BankResult::UNKNOWN],
            'tls failure' => [fn () => throw new \RuntimeException('cURL error 35: SSL connect error'), BankResult::UNKNOWN],
        ];
    }

    #[DataProvider('transferAnswers')]
    public function test_a_transfer_answer_is_only_done_or_refused_when_clear(\Closure $answer, string $expected): void
    {
        $this->fakeBank(['/disbursement/transfer' => fn () => $answer()]);

        $result = app(GtbankClient::class)->disbursementTransfer(self::PAYLOAD);

        $this->assertSame($expected, $result->outcome, $result->message());
    }

    public function test_a_409_on_a_resubmit_is_recognised_as_already_there(): void
    {
        $this->fakeBank(['/disbursement/transfer' => Http::response(['error' => 'Request Validation failed, please try again later'], 409)]);

        $this->assertTrue(app(GtbankClient::class)->disbursementTransfer(self::PAYLOAD)->duplicate());
    }

    public static function statusAnswers(): array
    {
        $ok = ['status' => 'ACSC', 'request_id' => self::REF, 'receiver_id' => '0788811607', 'cbs_transaction_reference' => 'M1'];

        return [
            'acsc for us' => [fn () => Http::response($ok), BankAnswers::COMPLETED],
            'acsc lowercase' => [fn () => Http::response(['status' => 'acsc'] + $ok), BankAnswers::COMPLETED],
            'acsc with whole amount' => [fn () => Http::response($ok + ['amount' => 1000]), BankAnswers::COMPLETED],
            'acsc with decimal amount string' => [fn () => Http::response($ok + ['amount' => '1000.00']), BankAnswers::COMPLETED],
            'acsc, +250 account' => [fn () => Http::response(['receiver_id' => '250788811607'] + $ok), BankAnswers::COMPLETED],
            'acsc about another reference' => [fn () => Http::response(['request_id' => 'CSH20261103000099'] + $ok), BankAnswers::INCONCLUSIVE],
            'acsc for another amount' => [fn () => Http::response($ok + ['amount' => 1001]), BankAnswers::INCONCLUSIVE],
            'acsc to another account' => [fn () => Http::response(['receiver_id' => '0788000000'] + $ok), BankAnswers::INCONCLUSIVE],
            'rjct' => [fn () => Http::response(['status' => 'RJCT'] + $ok), BankAnswers::FAILED],
            'canc' => [fn () => Http::response(['status' => 'CANC'] + $ok), BankAnswers::FAILED],
            'pending' => [fn () => Http::response(['status' => 'PENDING'] + $ok), BankAnswers::PENDING],
            'FAILED is not a transfer status' => [fn () => Http::response(['status' => 'FAILED'] + $ok), BankAnswers::INCONCLUSIVE],
            'status we do not know' => [fn () => Http::response(['status' => 'ON_HOLD'] + $ok), BankAnswers::INCONCLUSIVE],
            'no status at all' => [fn () => Http::response(['request_id' => self::REF]), BankAnswers::INCONCLUSIVE],
            'digipay error envelope with status FAILED' => [fn () => Http::response(['error' => 'Transaction not found for reference: '.self::REF, 'status' => 'FAILED', 'timestamp' => '2026-11-03T17:45:37']), BankAnswers::NOT_FOUND],
            'not found wrapped in 500' => [fn () => Http::response(['error' => 'Unable to retrieve disbursement status: Status check failed: 400  on POST request for "https://api.gtbank.rw/digipay/api/v2/digipay/check-status": "{"error":"Transaction not found for reference: '.self::REF.'","status":"FAILED","timestamp":"2026-11-03T17:45:37.104357517"}"'], 500), BankAnswers::NOT_FOUND],
            'status check down' => [fn () => Http::response(['error' => 'Unable to retrieve disbursement status: Status check failed: 503  on POST request for "https://api.gtbank.rw/x": [no body]'], 500), BankAnswers::INCONCLUSIVE],
            'status check html' => [fn () => Http::response('<html><title>Error</title></html>', 200), BankAnswers::INCONCLUSIVE],
        ];
    }

    #[DataProvider('statusAnswers')]
    public function test_a_disbursement_is_decided_only_by_a_status_about_it(\Closure $answer, string $expected): void
    {
        $this->fakeBank(['/disbursement/status' => fn () => $answer()]);

        $state = BankAnswers::disbursement(app(GtbankClient::class)->disbursementStatus(self::REF), self::REF, 100000, '0788811607');

        $this->assertSame($expected, $state['state'], (string) $state['reason']);
    }

    public function test_codes_arrive_as_numbers_or_strings(): void
    {
        $this->assertSame('00', BankCodes::normalise(0));
        $this->assertSame('01', BankCodes::normalise('1'));
        $this->assertSame('13', BankCodes::normalise(' 13 '));
        $this->assertNull(BankCodes::normalise(''));
    }
}
