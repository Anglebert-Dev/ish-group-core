<?php

namespace App\Support\Distribution;

/**
 * What a module package's ed25519 signature covers. Shared by our
 * Distribution module (signs) and every customer server (verifies), so the
 * two can never disagree. docs/self-hosting-build-guide.md §5.2, §6.
 */
final class PackageSignature
{
    public const CONTEXT = 'ish-module-v1';

    public static function message(string $module, string $version, string $sha256): string
    {
        return self::CONTEXT."\n{$module}\n{$version}\n{$sha256}";
    }

    /**
     * True when any of $publicKeys (base64) signed $message.
     *
     * @param  list<string>  $publicKeys
     */
    public static function verify(string $message, string $signature, array $publicKeys): bool
    {
        $signature = base64_decode($signature, true);

        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        foreach ($publicKeys as $key) {
            $key = base64_decode($key, true);

            if ($key !== false && strlen($key) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                && sodium_crypto_sign_verify_detached($signature, $message, $key)) {
                return true;
            }
        }

        return false;
    }
}
