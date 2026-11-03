<?php

namespace Modules\InjongePay\Gtbank;

/**
 * What a GTBank call came to. Callers decide on the outcome, never on the
 * message text:
 *  - ok: GTBank did it / answered.
 *  - not_found: GTBank has no such merchant, account or reference.
 *  - rejected: GTBank definitely refused (nothing happened at the bank).
 *  - unknown: timeout, 5xx, network: it may or may not have happened.
 */
final class BankResult
{
    public const OK = 'ok';

    public const NOT_FOUND = 'not_found';

    public const REJECTED = 'rejected';

    public const UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $outcome,
        public readonly array $data = [],
        public readonly ?int $httpStatus = null,
        public readonly ?string $code = null,
        public readonly ?string $message = null,
        public readonly ?int $callId = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->outcome === self::OK;
    }

    public function notFound(): bool
    {
        return $this->outcome === self::NOT_FOUND;
    }

    public function rejected(): bool
    {
        return $this->outcome === self::REJECTED;
    }

    public function unknown(): bool
    {
        return $this->outcome === self::UNKNOWN;
    }

    /** HTTP 409 or code 07/13: the reference was already used at GTBank. */
    public function duplicate(): bool
    {
        return $this->rejected() && ($this->httpStatus === 409 || in_array($this->code, ['07', '13'], true));
    }

    public function message(): string
    {
        return $this->message ?: __('injongepay::injongepay.bank_outcome_'.$this->outcome);
    }
}
