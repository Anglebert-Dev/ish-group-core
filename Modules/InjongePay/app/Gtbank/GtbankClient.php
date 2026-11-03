<?php

namespace Modules\InjongePay\Gtbank;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\InjongePay\Models\BankCall;
use Throwable;

/**
 * Transport for GTBank PaymentBridge (Integrator API v2.5). Business decisions
 * belong to the callers; this only sends, classifies the answer (BankResult)
 * and records every call in ijp_bank_calls.
 *
 * Only reads (GET) are retried on a network error. A POST that may have
 * reached the bank comes back as "unknown" and the caller asks for its status.
 */
class GtbankClient
{
    public const TOKEN_CACHE_KEY = 'injongepay.gtbank.token';

    private const REDACT = ['client_secret', 'access_token', 'authorization', 'password', 'x-gtbank-webhook-secret'];

    public static function isConfigured(): bool
    {
        return filled(config('injongepay.gtbank.base_url'));
    }

    public function registerMerchant(array $payload, ?Model $subject = null): BankResult
    {
        return $this->send('POST', '/merchants/register', $payload, subject: $subject, reference: $payload['merchantId'] ?? null);
    }

    public function updateMerchant(array $payload, ?Model $subject = null): BankResult
    {
        return $this->send('POST', '/merchants/update', $payload, subject: $subject, reference: $payload['merchantId'] ?? null);
    }

    public function closeMerchant(string $merchantId, ?Model $subject = null): BankResult
    {
        return $this->send('POST', '/merchants/close', ['merchantId' => $merchantId], subject: $subject, reference: $merchantId);
    }

    public function lookupMerchant(string $merchantId, ?Model $subject = null): BankResult
    {
        return $this->send('GET', '/merchants/lookup', query: ['merchantId' => $merchantId], subject: $subject, reference: $merchantId);
    }

    public function disbursementTransfer(array $payload, ?Model $subject = null): BankResult
    {
        $payload['beneficiaryAccount'] = self::beneficiary((string) ($payload['beneficiaryAccount'] ?? ''));

        return $this->send('POST', '/disbursement/transfer', $payload, subject: $subject, reference: $payload['reference'] ?? null);
    }

    public function disbursementLookup(string $identifier, ?Model $subject = null): BankResult
    {
        $identifier = self::beneficiary($identifier);

        return $this->send('GET', '/disbursement/lookup', query: ['identifier' => $identifier], subject: $subject, reference: $identifier);
    }

    /**
     * DigiPay only reads Rwandan wallet numbers as 07XXXXXXXX: "+250788…" fails
     * its lookup (seen in production) while "0788…" works. Bank accounts pass as they are.
     */
    public static function beneficiary(string $identifier): string
    {
        $clean = preg_replace('/[\s\-().]/', '', trim($identifier)) ?? '';

        if (preg_match('/^(?:\+?250|00250)(7\d{8})$/', $clean, $m)) {
            return '0'.$m[1];
        }

        return $clean;
    }

    public function disbursementStatus(string $reference, ?Model $subject = null): BankResult
    {
        return $this->send('GET', '/disbursement/status', query: ['reference' => $reference], subject: $subject, reference: $reference);
    }

    /** Transactions received by one merchant between two date-times (yyyy-MM-ddTHH:mm:ss). */
    public function merchantTransactions(string $merchantId, string $start, string $end, ?Model $subject = null): BankResult
    {
        return $this->send('GET', '/transactions', query: ['merchantId' => $merchantId, 'startDate' => $start, 'endDate' => $end], subject: $subject, reference: $merchantId);
    }

    public function participantTransactions(string $startDate, string $endDate): BankResult
    {
        return $this->send('GET', '/participant/transactions', query: ['startDate' => $startDate, 'endDate' => $endDate]);
    }

    /**
     * Records a call GTBank made to us (webhooks), in the same log.
     */
    public static function logInbound(string $endpoint, array $request, int $status, array $response, string $outcome, ?string $message, ?string $caller, ?string $reference = null, ?Model $subject = null, int $durationMs = 0): BankCall
    {
        return BankCall::query()->create([
            'business_id' => $subject?->getAttribute('business_id'),
            'direction' => 'inbound',
            'method' => 'POST',
            'endpoint' => $endpoint,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reference' => $reference,
            'request' => self::redact($request),
            'http_status' => $status,
            'response' => self::redact($response),
            'outcome' => $outcome,
            'message' => $message,
            'duration_ms' => $durationMs,
            'caller' => $caller,
        ]);
    }

    private function send(string $method, string $path, array $payload = [], array $query = [], ?Model $subject = null, ?string $reference = null): BankResult
    {
        if (! self::isConfigured()) {
            return new BankResult(BankResult::REJECTED, message: __('injongepay::injongepay.err_bank_not_configured'));
        }

        $started = microtime(true);
        $result = $this->attempt($method, $path, $payload, $query, retryToken: true);
        $duration = (int) ((microtime(true) - $started) * 1000);

        $call = BankCall::query()->create([
            'business_id' => $subject?->getAttribute('business_id'),
            'direction' => 'outbound',
            'method' => $method,
            'endpoint' => $path,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reference' => $reference,
            'request' => self::redact($method === 'GET' ? $query : $payload),
            'http_status' => $result->httpStatus,
            'response' => self::redact($result->data),
            'outcome' => $result->outcome,
            'bank_code' => $result->code,
            'message' => $result->diagnosis ? $result->diagnosis.' — '.$result->message : $result->message,
            'duration_ms' => $duration,
            'caller' => $subject && method_exists($subject, 'label') ? $subject->label() : null,
        ]);

        Log::channel('gtbank')->{$result->ok() || $result->notFound() ? 'info' : 'warning'}("GTBank {$method} {$path}: {$result->outcome}", [
                'call' => $call->uuid, 'http' => $result->httpStatus, 'code' => $result->code, 'message' => $result->message,
            ]);

        return $result->withCall($call->id);
    }

    private function attempt(string $method, string $path, array $payload, array $query, bool $retryToken): BankResult
    {
        try {
            $token = $this->token();
        } catch (Throwable $e) {
            report($e);

            return new BankResult(BankResult::UNKNOWN, message: __('injongepay::injongepay.err_bank_auth', ['reason' => $e->getMessage()]));
        }

        $tries = $method === 'GET' ? 2 : 1;
        // A transfer can take GTBank longer than 15 s and still go through (seen: timeout, then ACSC).
        $timeout = $path === '/disbursement/transfer' ? (int) config('injongepay.gtbank.transfer_timeout', 60) : (int) config('injongepay.gtbank.timeout', 20);

        for ($try = 1; $try <= $tries; $try++) {
            try {
                $request = $this->http()->timeout($timeout)->withToken($token);
                $response = $method === 'GET'
                    ? $request->get($this->url($path), $query)
                    : $request->post($this->url($path), $payload);
            } catch (ConnectionException $e) {
                if ($try < $tries) {
                    continue;
                }

                return new BankResult(BankResult::UNKNOWN, message: $e->getMessage(), diagnosis: __('injongepay::injongepay.diag_no_answer', ['seconds' => $timeout]));
            } catch (Throwable $e) {
                // TLS, DNS, a reset mid-answer: we can't know whether GTBank got it.
                report($e);

                return new BankResult(BankResult::UNKNOWN, message: $e->getMessage(), diagnosis: __('injongepay::injongepay.diag_no_answer', ['seconds' => $timeout]));
            }

            $result = $this->classify($response, $method, $path);

            if ($retryToken && ($response->status() === 401 || in_array($result->code, BankCodes::TOKEN_CODES, true))) {
                Cache::forget(self::TOKEN_CACHE_KEY);

                return $this->attempt($method, $path, $payload, $query, retryToken: false);
            }

            return $result;
        }

        return new BankResult(BankResult::UNKNOWN);
    }

    
    private function classify(Response $response, string $method, string $path): BankResult
    {
        $status = $response->status();
        [$body, $readable] = self::decode($response->body());
        $message = BankPayloads::error($body);
        $code = BankCodes::normalise($body['responseCode'] ?? $body['code'] ?? null);
        $java = isset($body['error']) || isset($body['timestamp']) || $status >= 400 ? JavaError::parse($body) : null;
        $result = fn (string $outcome, ?string $c = null) => new BankResult($outcome, $body, $status, $c ?? $code, $message, diagnosis: $java?->diagnosis() ?? ($readable ? null : __('injongepay::injongepay.diag_unreadable_body')), downstreamStatus: $java?->downstreamStatus);
        $write = $method !== 'GET';

        // Redirects (never followed), "continue", or nothing we can read: no answer.
        if ($status < 200 || ($status >= 300 && $status < 400) || ! $readable) {
            return $result(BankResult::UNKNOWN);
        }

        // What DigiPay said, even when the bridge wraps it in a 500 or a 200.
        if ($java?->saysNotFound()) {
            return $result(BankResult::NOT_FOUND);
        }

        if ($java?->kind === JavaError::UNREADABLE && $path === '/disbursement/lookup') {
            return $result(BankResult::REJECTED, 'UNREADABLE');
        }

        if ($status >= 500 || in_array($status, [408, 425, 429], true)) {
            return $result(BankResult::UNKNOWN);
        }

        if ($status === 409) {
            // Already there: often a write that timed out earlier but reached GTBank.
            return $result(BankResult::REJECTED, $code ?? '13');
        }

        if ($status === 404) {
            return $result(BankResult::NOT_FOUND);
        }

        if ($status >= 400) {
            // The bridge itself refused (validation, auth); a wrapped DigiPay answer stays unknown for a write.
            return $result($write && $java?->kind === JavaError::DOWNSTREAM_HTTP ? BankResult::UNKNOWN : BankResult::REJECTED);
        }

        if ($code !== null && $code !== '00') {
            $outcome = BankCodes::outcome($code);

            // An inactive merchant comes back as 01 with its data: that's an answer, not "not found".
            if ($outcome === BankResult::NOT_FOUND && ! empty($body['data'])) {
                $outcome = BankResult::OK;
            }

            return $result($outcome);
        }

        // 2xx carrying an error ({"error": "…"} and no answer fields).
        if (isset($body['error']) && ! self::hasAnswer($body)) {
            if (stripos((string) $body['error'], 'not found') !== false) {
                return $result(BankResult::NOT_FOUND);
            }

            return $result($write ? BankResult::UNKNOWN : BankResult::REJECTED);
        }

        // A write answered 2xx with an empty body: GTBank may or may not have taken it.
        if ($write && $body === []) {
            return $result(BankResult::UNKNOWN);
        }

        return $result(BankResult::OK, $code ?? '00');
    }

    /**
     * JSON, possibly with a BOM, wrapped in quotes, or not JSON at all (an HTML
     * proxy page, a truncated body).
     *
     * @return array{0: array, 1: bool} body, and whether it was readable
     */
    public static function decode(string $raw): array
    {
        $raw = trim(preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw);

        if ($raw === '') {
            return [[], true];
        }

        $decoded = json_decode($raw, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (is_array($decoded)) {
            return [$decoded, true];
        }

        $title = preg_match('/<title>(.*?)<\/title>/is', $raw, $m) ? trim(strip_tags($m[1])) : null;

        return [array_filter(['raw' => mb_substr($raw, 0, 2000), 'html_title' => $title]), false];
    }

    private static function hasAnswer(array $body): bool
    {
        return isset($body['names']) || isset($body['identifier']) || isset($body['request_id']) || isset($body['merchantId'])
            || (isset($body['status']) && ! isset($body['timestamp']) && in_array(strtoupper((string) $body['status']), ['ACSC', 'PENDING', 'CANC', 'RJCT', 'SUCCESS', 'ACTIVE'], true));
    }

    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return (function () {
            $response = $this->http(json: false)->asForm()->post($this->url('/auth/token'), [
                'grant_type' => 'client_credentials',
                'client_id' => config('injongepay.gtbank.client_id'),
                'client_secret' => config('injongepay.gtbank.client_secret'),
            ]);

            $token = $response->json('access_token');

            BankCall::query()->create([
                'direction' => 'outbound', 'method' => 'POST', 'endpoint' => '/auth/token',
                'request' => ['grant_type' => 'client_credentials', 'client_id' => config('injongepay.gtbank.client_id')],
                'http_status' => $response->status(), 'response' => self::redact((array) $response->json()),
                'outcome' => $token ? BankResult::OK : BankResult::REJECTED,
                'message' => $token ? null : BankPayloads::error((array) $response->json()),
            ]);

            if (! $response->successful() || ! $token) {
                throw new \RuntimeException(BankPayloads::error((array) $response->json()) ?: 'HTTP '.$response->status());
            }

            // Kept until a minute before it expires (GTBank gives 3600 to 86400 seconds).
            Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, (int) $response->json('expires_in', 3600) - 60));

            return $token;
        })();
    }

    private function http(bool $json = true): PendingRequest
    {
        $request = Http::withoutRedirecting()->timeout((int) config('injongepay.gtbank.timeout', 20))
            ->withOptions(['verify' => $this->verify()])
            ->withHeaders(array_filter(['X-Participant-Name' => config('injongepay.gtbank.participant_name')]));

        return $json ? $request->acceptJson()->asJson() : $request->acceptJson();
    }

    private function verify(): bool|string
    {
        if (! filter_var(config('injongepay.gtbank.ssl_verify', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $ca = (string) config('injongepay.gtbank.ca_path');

        return $ca !== '' && is_file($ca) ? $ca : true;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('injongepay.gtbank.base_url'), '/').$path;
    }

    public static function redact(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            $data[$key] = in_array(strtolower((string) $key), self::REDACT, true) ? '[REDACTED]' : self::redact($value);
        }

        return $data;
    }
}
