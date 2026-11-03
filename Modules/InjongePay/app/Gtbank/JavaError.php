<?php

namespace Modules\InjongePay\Gtbank;

/**
 * Reads the errors GTBank's PaymentBridge really returns. It is a Spring
 * (Java) service in front of DigiPay; when DigiPay answers badly the bridge
 * answers HTTP 500 and buries the real answer in its message:
 *
 *   RestTemplate: "Status check failed: 400  on POST request for \"https://…/check-status\": \"{\"error\":\"Transaction not found…\",\"status\":\"FAILED\"}\""
 *   RestTemplate: "Disbursement initiation failed: 401  on POST request for \"https://…/pay\": [no body]"
 *   Jackson:      "Lookup failed: Error while extracting response for type [class …DigiPayLookupResponse] and content type [application/json]"
 *   I/O:          "I/O error on POST request for \"…\": Read timed out" / "Connection refused" / "connect timed out"
 *   Spring Boot:  {"timestamp": "…", "status": 500, "error": "Internal Server Error", "path": "/…"}
 */
final class JavaError
{
    public const DOWNSTREAM_HTTP = 'downstream_http';

    public const UNREADABLE = 'unreadable';

    public const IO = 'io';

    public const SPRING_DEFAULT = 'spring_default';

    public const OTHER = 'other';

    public function __construct(
        public readonly string $kind,
        public readonly ?int $downstreamStatus = null,
        public readonly ?string $downstreamUrl = null,
        public readonly array $downstreamBody = [],
        public readonly string $text = '',
    ) {
    }

    public static function parse(array $body): ?self
    {
        $text = BankPayloads::error($body);

        // Spring Boot's own error page: {"timestamp","status","error","path"}.
        if (isset($body['timestamp'], $body['status'], $body['path']) && is_numeric($body['status'])) {
            return new self(self::SPRING_DEFAULT, (int) $body['status'], $body['path'], [], (string) ($body['message'] ?? $body['error'] ?? ''));
        }

        if ($text === null) {
            return null;
        }

        if (preg_match('/(\d{3})\s+[A-Za-z ]*\s*on (?:GET|POST|PUT|DELETE|PATCH) request for "([^"]+)"(?::\s*(.*))?$/s', $text, $m)) {
            return new self(self::DOWNSTREAM_HTTP, (int) $m[1], $m[2], self::embeddedJson($m[3] ?? ''), $text);
        }

        if (stripos($text, 'Error while extracting response') !== false || stripos($text, 'JSON parse error') !== false || stripos($text, 'Cannot deserialize') !== false) {
            return new self(self::UNREADABLE, text: $text);
        }

        if (preg_match('/I\/O error|Read timed out|connect timed out|Connection refused|Connection reset|SocketTimeoutException|UnknownHostException|No route to host/i', $text)) {
            return new self(self::IO, text: $text);
        }

        return new self(self::OTHER, text: $text);
    }

    /** DigiPay said it has no such transaction or account. */
    public function saysNotFound(): bool
    {
        if ($this->downstreamStatus === 404) {
            return true;
        }

        $inner = strtolower((string) ($this->downstreamBody['error'] ?? $this->downstreamBody['message'] ?? ''));

        return $this->kind === self::DOWNSTREAM_HTTP && $this->downstreamStatus !== null && $this->downstreamStatus < 500
            && str_contains($inner === '' ? strtolower($this->text) : $inner, 'not found');
    }

    /** A sentence for people: who refused what. */
    public function diagnosis(): string
    {
        return match ($this->kind) {
            self::DOWNSTREAM_HTTP => match (true) {
                in_array($this->downstreamStatus, [401, 403], true) => __('injongepay::injongepay.diag_downstream_auth', ['status' => $this->downstreamStatus]),
                $this->saysNotFound() => __('injongepay::injongepay.diag_downstream_not_found'),
                $this->downstreamStatus >= 500 => __('injongepay::injongepay.diag_downstream_down', ['status' => $this->downstreamStatus]),
                default => __('injongepay::injongepay.diag_downstream_refused', ['status' => $this->downstreamStatus, 'reason' => $this->downstreamBody['error'] ?? $this->downstreamBody['message'] ?? '—']),
            },
            self::UNREADABLE => __('injongepay::injongepay.diag_unreadable'),
            self::IO => __('injongepay::injongepay.diag_io'),
            self::SPRING_DEFAULT => __('injongepay::injongepay.diag_spring', ['status' => $this->downstreamStatus]),
            default => $this->text,
        };
    }

    /** The downstream body after ": ", which is itself JSON inside quotes (or "[no body]"). */
    private static function embeddedJson(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === '[no body]') {
            return [];
        }

        if (str_starts_with($raw, '"') && str_ends_with($raw, '"')) {
            $raw = substr($raw, 1, -1);
        }

        $decoded = json_decode(stripcslashes($raw), true) ?? json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['raw' => mb_substr($raw, 0, 500)];
    }
}
