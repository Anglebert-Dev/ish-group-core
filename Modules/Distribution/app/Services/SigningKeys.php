<?php

namespace Modules\Distribution\Services;

use App\Services\Install\EnvWriter;
use Illuminate\Support\Facades\Artisan;
use Modules\Distribution\Support\PlatformAudit;
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

    /**
     * Make a key pair and save it in .env (the web button and
     * distribution:keygen both land here). Earlier public keys stay listed,
     * so sites built before a key change still accept what they had.
     *
     * @return array{secret: string, public: string} base64
     *
     * @throws \InvalidArgumentException when .env cannot be written
     */
    public function create(): array
    {
        $pair = self::generate();
        $public = array_values(array_unique([$pair['public'], ...(array) config('distribution.public_keys', [])]));

        app(EnvWriter::class)->set([
            'DISTRIBUTION_SIGNING_SECRET' => $pair['secret'],
            'DISTRIBUTION_PUBLIC_KEYS' => implode(',', $public),
        ]);

        config(['distribution.signing_secret' => $pair['secret'], 'distribution.public_keys' => $public]);

        // A cached config would keep running without the new key.
        if (app()->configurationIsCached()) {
            Artisan::call('config:cache');
        }

        PlatformAudit::log('distribution_keys_generated', 'Release signing key created', null, ['public_key' => $pair['public']]);

        return $pair;
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
