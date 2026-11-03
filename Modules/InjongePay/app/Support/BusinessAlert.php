<?php

namespace Modules\InjongePay\Support;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\Business\Business;
use App\Models\User;
use App\Notifications\BrandedNotification;
use App\Services\Notification\NotificationService;
use Throwable;

/**
 * A security change in a business (a PIN, a settlement account, a limit):
 * its Admins hear about it in the app and by email, and so does the person
 * it concerns. Never stops the change itself.
 */
final class BusinessAlert
{
    public static function send(int $businessId, string $title, string $body, string $url, ?User $alsoTell = null): void
    {
        $business = Business::query()->find($businessId);

        if (! $business) {
            return;
        }

        $users = User::query()->where('business_id', $businessId)->where('status', UserStatus::Active->value)->get()
            ->filter(fn (User $user) => $user->isBusinessAdmin() || $user->is($alsoTell));

        if ($alsoTell && $users->doesntContain(fn (User $u) => $u->is($alsoTell))) {
            $users->push($alsoTell);
        }

        foreach ($users as $user) {
            try {
                app(NotificationService::class)->notify($user, $title, $body, NotificationType::Security, $url);
                $user->notify(new BrandedNotification($business, $title, [$body, __('injongepay::injongepay.alert_not_you')], __('body.view'), url($url)));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
