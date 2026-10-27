<?php

namespace Modules\Billing\Services;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\Business\Business;
use App\Models\User;
use App\Notifications\BrandedNotification;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Support\BillingSettings;

/**
 * Billing events reach people twice: an in-app notification for the
 * business's billing managers (and Admins), and an email to its billing
 * address — sent in the platform's (invoice issuer's) name.
 */
class BillingNotifier
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function business(Business $business, string $title, ?string $body = null, NotificationType $type = NotificationType::Info, ?string $url = null): void
    {
        $url ??= Route::has('billing.index') ? route('billing.index') : null;

        $this->notifications->notifyByPermission('manage billing.subscription', $title, $body, $type, $url, null, $business->id);

        $email = BillingAccount::query()->where('business_id', $business->id)->value('billing_email') ?: $business->contactEmail();

        if ($email) {
            Notification::route('mail', $email)->notify($this->mail($title, $body, $url));
        }
    }

    public function superadmins(string $title, ?string $body = null, ?string $url = null): void
    {
        User::query()
            ->where('is_superadmin', true)
            ->where('status', UserStatus::Active->value)
            ->get()
            ->each(function (User $user) use ($title, $body, $url) {
                $this->notifications->notify($user, $title, $body, NotificationType::Info, $url);
                $user->notify($this->mail($title, $body, $url ? url($url) : null));
            });
    }

    private function mail(string $title, ?string $body, ?string $url): BrandedNotification
    {
        $notification = new BrandedNotification(
            null,
            $title,
            array_values(array_filter([$body])),
            $url ? __('billing::billing.email_open_billing') : null,
            $url,
            [],
            BillingSettings::get('issuer_email') ?: null,
            BillingSettings::get('issuer_name') ?: null,
        );

        return $notification;
    }
}
