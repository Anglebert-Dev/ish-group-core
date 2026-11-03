<?php

namespace Modules\InjongePay\Support;

use App\Models\System\System;
use Illuminate\Support\Facades\Crypt;

/**
 * Platform-wide Injonge Pay settings in the core system table ("injongepay_<key>").
 * The webhook secret is stored encrypted.
 */
final class PlatformSettings
{
    public const DEFAULTS = [
        'fee_percentage' => null,
        'bank_cut_percentage' => null,
        'webhook_secret' => null,
    ];

    public static function feePercentage(): string
    {
        return self::rate('fee_percentage', (string) config('injongepay.default_fee_percentage', '2.00'));
    }

    public static function bankCutPercentage(): string
    {
        return self::rate('bank_cut_percentage', (string) config('injongepay.default_bank_cut_percentage', '0.00'));
    }

    public static function webhookSecret(): ?string
    {
        $stored = System::getProperty('injongepay_webhook_secret');

        return $stored ? Crypt::decryptString($stored) : (env('GTBANK_WEBHOOK_SECRET') ?: null);
    }

    public static function setWebhookSecret(string $secret): void
    {
        System::addProperty('injongepay_webhook_secret', Crypt::encryptString($secret));
    }

    /**
     * Addresses GTBank sends webhooks from (comma-separated IPs or CIDR ranges).
     * GTBank does not send the secret header (production had to switch the check
     * off), so its own address is how its webhooks are recognised.
     *
     * @return list<string>
     */
    public static function webhookAllowedIps(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) System::getProperty('injongepay_webhook_allowed_ips', '')))));
    }

    public static function set(string $key, ?string $value): void
    {
        System::addProperty('injongepay_'.$key, $value);
    }

    private static function rate(string $key, string $default): string
    {
        $stored = System::getProperty('injongepay_'.$key);

        return $stored !== null && $stored !== '' ? bcadd((string) $stored, '0', 2) : bcadd($default, '0', 2);
    }
}
