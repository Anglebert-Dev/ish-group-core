<?php

namespace Database\Seeders;

use App\Models\System\System;
use App\Models\User;
use App\Services\Business\BusinessSetupService;
use App\Services\Permission\PermissionService;
use Illuminate\Database\Seeder;

/**
 * Local/demo data only. For a real install use `php artisan core:install`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionService::class)->initPermissions(true);

        if (! app()->environment('local', 'testing') || User::query()->withTrashed()->exists()) {
            return;
        }

        $result = app(BusinessSetupService::class)->createWithOwner(
            ['name' => 'Demo Business'],
            [
                'name' => 'Demo Admin',
                'email' => 'admin@example.com',
                'password' => 'Demo-Admin-Pass-2026!',
                'is_superadmin' => true,
            ],
        );

        System::addProperty('primary_business_id', $result['data']['business']->id);

        $this->command?->info('Demo superadmin: admin@example.com / Demo-Admin-Pass-2026!');
    }
}
