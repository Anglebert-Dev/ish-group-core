<?php

namespace Modules\Billing\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';       // waiting for the first payment
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';      // period over, unpaid, inside the grace period
    case Suspended = 'suspended';   // grace over: paid modules locked until paid
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('billing::billing.status_pending'),
            self::Trialing => __('billing::billing.status_trialing'),
            self::Active => __('billing::billing.status_active'),
            self::PastDue => __('billing::billing.status_past_due'),
            self::Suspended => __('billing::billing.status_suspended'),
            self::Canceled => __('billing::billing.status_canceled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Trialing => 'brand',
            self::Pending, self::PastDue => 'warning',
            self::Suspended => 'danger',
            self::Canceled => 'muted',
        };
    }

    /** Statuses that still count as "the business's subscription". */
    public static function current(): array
    {
        return [self::Pending, self::Trialing, self::Active, self::PastDue, self::Suspended];
    }

    /** Statuses that give access to the package's modules. */
    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }
}
