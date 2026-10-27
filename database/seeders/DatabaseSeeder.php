<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Permission\PermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionService::class)->initPermissions(true);

        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ],
        );

        $user->status = UserStatus::Active;
        $user->save();

        if (! $user->hasRole('SuperAdmin')) {
            $user->assignRole('SuperAdmin');
        }

        $this->call(DemoFleetSeeder::class);
        $this->call(ChartOfAccountSeeder::class);
        $this->call(DemoBookingSeeder::class);
    }
}
