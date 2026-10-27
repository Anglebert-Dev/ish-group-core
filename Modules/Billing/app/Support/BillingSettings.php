<?php

namespace Modules\Billing\Support;

use App\Models\System\System;

/**
 * Platform billing settings, stored in the core `system` table as
 * "billing_<key>".
 */
class BillingSettings
{
    public const DEFAULTS = [
        'currency' => 'RWF',
        'tax_rate' => '0',           // percent, e.g. 18
        'tax_inclusive' => '0',      // 1 = prices already include tax
        'tax_label' => 'VAT',
        'grace_days' => '7',         // after the period ends unpaid
        'reminder_days' => '7',      // renewal invoice issued this many days before the end
        'due_days' => '7',           // payment terms for new invoices
        'invoice_prefix' => 'INV',
        'default_package_id' => '',  // auto-subscribe new businesses
        'payment_instructions' => '',
        'issuer_name' => '',
        'issuer_address' => '',
        'issuer_tax_id' => '',
        'issuer_email' => '',
        'issuer_phone' => '',
    ];

    public static function get(string $key): string
    {
        return (string) (self::all()[$key] ?? '');
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        // Memoised per request (a scoped binding, flushed after each request).
        return app('billing.settings');
    }

    /**
     * @return array<string, string>
     */
    public static function load(): array
    {
        $stored = System::getProperties(array_map(fn ($k) => 'billing_'.$k, array_keys(self::DEFAULTS)));
        $values = self::DEFAULTS;

        foreach ($stored as $key => $value) {
            if ($value !== null) {
                $values[substr($key, strlen('billing_'))] = (string) $value;
            }
        }

        return $values;
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                System::addProperty('billing_'.$key, $value === null ? '' : (string) $value);
            }
        }

        self::flush();
    }

    public static function flush(): void
    {
        app()->forgetInstance('billing.settings');
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }
}
