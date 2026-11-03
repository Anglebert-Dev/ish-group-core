<?php

namespace Modules\InjongePay\Support;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\System\System;
use App\Models\User;
use App\Notifications\BrandedNotification;
use App\Services\Notification\NotificationService;
use Throwable;

/**
 * Something the platform's staff must act on (money held, a mismatch with
 * GTBank, a stuck cash-out): in the app and by email, to every active
 * operator user who holds the permission, and to superadmins.
 */
final class PlatformAlert
{
    public static function send(string $permission, string $title, string $body, string $url): void
    {
        $operator = (int) System::getProperty('primary_business_id');

        $users = User::query()->where('status', UserStatus::Active->value)
            ->where(fn ($q) => $q->where('is_superadmin', true)->orWhere('business_id', $operator))
            ->get()
            ->filter(fn (User $user) => $user->isSuperAdmin() || $user->can($permission));

        foreach ($users as $user) {
            try {
                app(NotificationService::class)->notify($user, $title, $body, NotificationType::Warning, $url);
                $user->notify(new BrandedNotification(null, $title, [$body], __('body.view'), url($url)));
            } catch (Throwable $e) {
                // An alert that can't be sent must never stop the money operation.
                report($e);
            }
        }
    }
}
