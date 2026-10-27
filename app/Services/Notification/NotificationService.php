<?php

namespace App\Services\Notification;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Libraries\GeneralLibrary;
use App\Libraries\ListExport;
use App\Models\Notification\Notification;
use App\Models\User;
use App\Repositories\Notification\NotificationRepository;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class NotificationService extends BaseService
{
    public function __construct(protected NotificationRepository $notifications)
    {
    }

    public function listForUser(?User $user = null): LengthAwarePaginator
    {
        $user ??= auth('staff')->user();

        return $this->notifications->forUser((int) $user->id);
    }

    public function exportList(?User $user = null): ListExport
    {
        $user ??= auth('staff')->user();

        $rows = $this->notifications->query()
            ->where('user_id', (int) $user->id)
            ->latest()
            ->get()
            ->map(fn (Notification $notification) => [
                $notification->status->label(),
                $notification->title,
                $notification->type->label(),
                GeneralLibrary::formatDateTime($notification->created_at),
            ])
            ->all();

        return ListExport::make(__('body.notifications'), 'notifications', [
            __('body.status'),
            __('body.notification'),
            __('body.type'),
            __('body.when'),
        ], $rows);
    }

    public function recentForHeader(?User $user = null, int $limit = 8): Collection
    {
        if (! $this->ready() || ! $user) {
            return new Collection;
        }

        return $this->notifications->recentForUser((int) $user->id, $limit);
    }

    public function unreadCount(?User $user = null): int
    {
        if (! $this->ready() || ! $user) {
            return 0;
        }

        return $this->notifications->unreadCountForUser((int) $user->id);
    }

    public function markAsRead(Notification $notification, ?User $user = null): array
    {
        $user ??= auth('staff')->user();

        if ((int) $notification->user_id !== (int) $user->id) {
            return $this->failure(__('body.no_permission'));
        }

        if ($notification->isUnread()) {
            $notification->status = NotificationStatus::Read;
            $notification->read_at = now();
            $this->notifications->save($notification);
        }

        return $this->success($notification, __('body.notification_marked_read'));
    }

    public function markAllAsRead(?User $user = null): array
    {
        $user ??= auth('staff')->user();

        $this->notifications->query()
            ->where('user_id', $user->id)
            ->where('status', NotificationStatus::Unread->value)
            ->update([
                'status' => NotificationStatus::Read->value,
                'read_at' => now(),
                'update_by' => $user->id,
            ]);

        return $this->success(null, __('body.notifications_marked_read'));
    }

    /**
     * Create an in-app notification for a staff user.
     */
    public function notify(
        User|int $user,
        string $title,
        ?string $body = null,
        NotificationType|string $type = NotificationType::System,
        ?string $actionUrl = null,
        ?Model $subject = null,
    ): array {
        if (! $this->ready()) {
            return $this->failure(__('body.something_went_wrong'));
        }

        $userId = $user instanceof User ? $user->id : $user;
        $typeValue = $type instanceof NotificationType ? $type->value : $type;

        $notification = new Notification([
            'user_id' => $userId,
            'type' => $typeValue,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'status' => NotificationStatus::Unread->value,
        ]);

        $this->notifications->save($notification);

        return $this->success($notification, __('body.notification_created'));
    }

    /**
     * Notify every staff user who has the given permission.
     * Falls back to all active staff if the permission is missing or unused.
     *
     * @return array{status: bool, message: string, data: int}
     */
    public function notifyByPermission(
        string $permission,
        string $title,
        ?string $body = null,
        NotificationType|string $type = NotificationType::System,
        ?string $actionUrl = null,
        ?Model $subject = null,
    ): array {
        if (! $this->ready()) {
            return $this->failure(__('body.something_went_wrong'));
        }

        $users = collect();

        try {
            $users = User::query()
                ->permission($permission)
                ->where('status', \App\Enums\UserStatus::Active->value)
                ->get();
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            $users = collect();
        }

        if ($users->isEmpty()) {
            $users = User::query()
                ->where('status', \App\Enums\UserStatus::Active->value)
                ->get();
        }

        $created = 0;

        foreach ($users as $user) {
            $result = $this->notify($user, $title, $body, $type, $actionUrl, $subject);

            if ($result['status']) {
                $created++;
            }
        }

        return $this->success($created, __('body.notification_created'));
    }

    protected function ready(): bool
    {
        return Schema::hasTable('notifications');
    }
}
