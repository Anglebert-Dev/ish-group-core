<?php

namespace Modules\Distribution\Services;

use RuntimeException;

/**
 * Our ed25519 key pair. The secret lives only in .env
 * (DISTRIBUTION_SIGNING_SECRET); the public key goes into every zip.
 */
class SigningKeys
{
    /**
     * @return array{secret: string, public: string} base64
     */
    public static function generate(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
        ];
    }

    public function configured(): bool
    {
        try {
            $this->secret();
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    public function publicKey(): string
    {
        return base64_encode(sodium_crypto_sign_publickey_from_secretkey($this->secret()));
    }

    /**
     * Detached signature of $message, base64.
     */
    public function sign(string $message): string
    {
        return base64_encode(sodium_crypto_sign_detached($message, $this->secret()));
    }

    private function secret(): string
    {
        $secret = base64_decode((string) config('distribution.signing_secret'), true);

        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException(__('distribution::distribution.keys_missing'));
        }

        return $secret;
    }
}
