<?php

namespace Modules\Billing\Services;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\Business\Business;
use App\Models\User;
use App\Services\Notification\NotificationService;

/**
 * In-app notifications for billing events: the business's billing managers
 * (and its Admins), and platform superadmins for things they must act on.
 */
class BillingNotifier
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function business(Business $business, string $title, ?string $body = null, NotificationType $type = NotificationType::Info, ?string $url = null): void
    {
        $this->notifications->notifyByPermission(
            'manage billing.subscription',
            $title,
            $body,
            $type,
            $url ?? (\Illuminate\Support\Facades\Route::has('billing.index') ? route('billing.index', [], false) : null),
            null,
            $business->id,
        );
    }

    public function superadmins(string $title, ?string $body = null, ?string $url = null): void
    {
        User::query()
            ->where('is_superadmin', true)
            ->where('status', UserStatus::Active->value)
            ->get()
            ->each(fn (User $user) => $this->notifications->notify($user, $title, $body, NotificationType::Info, $url));
    }
}
