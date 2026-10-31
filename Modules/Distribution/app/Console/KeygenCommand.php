<?php

namespace Modules\Distribution\Console;

use Illuminate\Console\Command;
use Modules\Distribution\Services\SigningKeys;

/**
 * Terminal twin of Self-hosting → Releases → Create signing key: makes our
 * key pair and writes it to .env. Run once on our server.
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

        $pair = $keys->create();

        $this->components->info(__('distribution::distribution.keys_created'));
        $this->line('  '.$pair['public']);
        $this->components->warn(__('distribution::distribution.keys_backup'));

        return self::SUCCESS;
    }
}
