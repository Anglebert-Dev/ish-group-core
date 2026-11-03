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
        return $this->send('POST', '/disbursement/transfer', $payload, subject: $subject, reference: $payload['reference'] ?? null);
    }

    public function disbursementLookup(string $identifier, ?Model $subject = null): BankResult
    {
        return $this->send('GET', '/disbursement/lookup', query: ['identifier' => $identifier], subject: $subject, reference: $identifier);
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
            'message' => $result->message,
            'duration_ms' => $duration,
            'caller' => $subject && method_exists($subject, 'label') ? $subject->label() : null,
        ]);

        Log::channel('gtbank')->{$result->ok() || $result->notFound() ? 'info' : 'warning'}("GTBank {$method} {$path}: {$result->outcome}", [
                'call' => $call->uuid, 'http' => $result->httpStatus, 'code' => $result->code, 'message' => $result->message,
            ]);

        return new BankResult($result->outcome, $result->data, $result->httpStatus, $result->code, $result->message, $call->id);
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

        for ($try = 1; $try <= $tries; $try++) {
            try {
                $request = $this->http()->withToken($token);
                $response = $method === 'GET'
                    ? $request->get($this->url($path), $query)
                    : $request->post($this->url($path), $payload);
            } catch (ConnectionException $e) {
                if ($try < $tries) {
                    continue;
                }

                return new BankResult(BankResult::UNKNOWN, message: $e->getMessage());
            }

            $result = $this->classify($response);

            if ($retryToken && ($response->status() === 401 || in_array($result->code, BankCodes::TOKEN_CODES, true))) {
                Cache::forget(self::TOKEN_CACHE_KEY);

                return $this->attempt($method, $path, $payload, $query, retryToken: false);
            }

            return $result;
        }

        return new BankResult(BankResult::UNKNOWN);
    }

    private function classify(Response $response): BankResult
    {
        $status = $response->status();
        $body = $response->json();
        $body = is_array($body) ? $body : ['raw' => mb_substr($response->body(), 0, 2000)];
        $message = BankPayloads::error($body);
        $code = isset($body['responseCode']) ? (string) $body['responseCode'] : null;

        if ($status >= 500) {
            return new BankResult(BankResult::UNKNOWN, $body, $status, $code, $message);
        }

        if ($status === 404) {
            return new BankResult(BankResult::NOT_FOUND, $body, $status, $code, $message);
        }

        if ($status === 409) {
            return new BankResult(BankResult::REJECTED, $body, $status, $code ?? '13', $message);
        }

        if ($status >= 400) {
            return new BankResult(BankResult::REJECTED, $body, $status, $code, $message);
        }

        if ($code !== null && $code !== '00') {
            $outcome = BankCodes::outcome($code);

            // An inactive merchant comes back as 01 with its data: that's an answer, not "not found".
            if ($outcome === BankResult::NOT_FOUND && ! empty($body['data'])) {
                $outcome = BankResult::OK;
            }

            return new BankResult($outcome, $body, $status, $code, $message);
        }

        // Lookups and status checks answer 200 {"error": "… not found …"} for an unknown identifier.
        if (isset($body['error']) && ! isset($body['status']) && ! isset($body['names'])) {
            return new BankResult(stripos((string) $body['error'], 'not found') !== false ? BankResult::NOT_FOUND : BankResult::REJECTED, $body, $status, $code, $message);
        }

        return new BankResult(BankResult::OK, $body, $status, $code ?? '00', $message);
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
        $request = Http::timeout((int) config('injongepay.gtbank.timeout', 15))
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
