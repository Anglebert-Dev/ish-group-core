<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Business\PlatformSetupService;
use App\Services\Permission\PermissionService;
use App\Support\Installer\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Bootstraps a fresh install: migrate, seed permissions, create the first
 * business and its owner as platform superadmin (reference doc §4.2, without
 * the .env username list).
 */
class InstallCoreCommand extends Command
{
    protected $signature = 'core:install
        {--business= : Name of the first business}
        {--name= : Superadmin full name}
        {--email= : Superadmin email}
        {--password= : Superadmin password (prompted when omitted)}
        {--force : Run migrations without confirmation in production}';

    protected $description = 'Migrate the database and create the first business with a superadmin';

    public function handle(PlatformSetupService $setup, PermissionService $permissions): int
    {
        $this->call('migrate', ['--force' => (bool) $this->option('force')]);

        $permissions->initPermissions(true);

        if (User::query()->withTrashed()->exists()) {
            Installer::markInstalled();
            $this->components->info('Already installed — users exist. Nothing else to do.');

            return self::SUCCESS;
        }

        $data = [
            'business' => $this->option('business') ?: $this->ask('Business name', config('app.name')),
            'name' => $this->option('name') ?: $this->ask('Your full name'),
            'email' => $this->option('email') ?: $this->ask('Your email'),
            'password' => $this->option('password') ?: $this->secret('Password (min 12, mixed case, number, symbol)'),
        ];

        $validator = Validator::make($data, [
            'business' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $setup->createFirstPlatform($data);
        Installer::markInstalled();

        $this->components->info('Installed. Sign in at '.url('/app/login')." as {$data['email']}.");

        return self::SUCCESS;
    }
}
