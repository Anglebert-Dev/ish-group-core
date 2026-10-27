<?php

namespace App\Enums;

enum PaymentOption: string
{
    case PayNow = 'pay_now';
    case PayAtPickup = 'pay_at_pickup';
    case CorporateBilling = 'corporate_billing';

    public function label(): string
    {
        return match ($this) {
            self::PayNow => __('body.pay_now'),
            self::PayAtPickup => __('body.pay_at_pickup'),
            self::CorporateBilling => __('body.corporate_billing'),
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::PayNow => __('body.pay_now_hint'),
            self::PayAtPickup => __('body.pay_at_pickup_hint'),
            self::CorporateBilling => __('body.corporate_billing_hint'),
        };
    }

    /** Payment is deferred — collect later at pickup or via corporate invoice. */
    public function isPayLater(): bool
    {
        return $this === self::PayAtPickup || $this === self::CorporateBilling;
    }

    /**
     * @return list<self>
     */
    public static function forCustomerType(string $customerType): array
    {
        return match ($customerType) {
            'corporate' => [self::CorporateBilling, self::PayAtPickup, self::PayNow],
            default => [self::PayAtPickup, self::PayNow],
        };
    }

    public static function defaultForCustomerType(string $customerType): self
    {
        return $customerType === 'corporate'
            ? self::CorporateBilling
            : self::PayAtPickup;
    }
}
