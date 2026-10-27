<?php

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(NotificationService::class);

        User::query()->each(function (User $user) use ($service) {
            if (\App\Models\Notification\Notification::query()->where('user_id', $user->id)->exists()) {
                return;
            }

            $service->notify(
                $user,
                'Welcome to the staff portal',
                'You will receive booking, finance, and system alerts here.',
                NotificationType::System,
                '/app/notifications',
            );

            $service->notify(
                $user,
                'Finance ledger is ready',
                'Chart of accounts, journal entries, and statements are available under Finance.',
                NotificationType::Finance,
                '/app/finance',
            );

            $service->notify(
                $user,
                'Review open bookings',
                'Check the bookings list for reservations that need confirmation or vehicle assignment.',
                NotificationType::Booking,
                '/app/bookings',
            );
        });
    }
}
