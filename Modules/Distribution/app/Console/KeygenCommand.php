<?php

namespace Modules\Distribution\Console;

use App\Services\Install\EnvWriter;
use Illuminate\Console\Command;
use Modules\Distribution\Services\SigningKeys;
use Modules\Distribution\Support\PlatformAudit;

/**
 * Makes our signing key pair and writes it to .env. Run once on our server.
 * Replacing a key (--force) means sites need the new public key (a new zip)
 * before they accept anything signed with it.
 */
class KeygenCommand extends Command
{
    protected $signature = 'distribution:keygen {--force : Replace an existing key}';

    protected $description = 'Create the key that signs module releases (written to .env)';

    public function handle(SigningKeys $keys): int
    {
        if ($keys->configured() && ! $this->option('force')) {
            $this->components->info(__('distribution::distribution.keys_exist'));
            $this->line('  '.$keys->publicKey());

            return self::SUCCESS;
        }

        $pair = SigningKeys::generate();
        $public = array_values(array_unique([$pair['public'], ...(array) config('distribution.public_keys', [])]));

        (new EnvWriter)->set([
            'DISTRIBUTION_SIGNING_SECRET' => $pair['secret'],
            'DISTRIBUTION_PUBLIC_KEYS' => implode(',', $public),
        ]);

        config(['distribution.signing_secret' => $pair['secret'], 'distribution.public_keys' => $public]);
        PlatformAudit::log('distribution_keys_generated', 'Release signing key created', null, ['public_key' => $pair['public']]);

        $this->components->info(__('distribution::distribution.keys_created'));
        $this->line('  '.$pair['public']);
        $this->components->warn(__('distribution::distribution.keys_backup'));

        return self::SUCCESS;
    }
}
