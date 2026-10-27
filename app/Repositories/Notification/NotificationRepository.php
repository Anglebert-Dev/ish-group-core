<?php

namespace App\Repositories\Notification;

use App\Enums\NotificationStatus;
use App\Models\Notification\Notification;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class NotificationRepository extends BaseRepository
{
    public function __construct(Notification $model)
    {
        parent::__construct($model);
    }

    public function forUser(int $userId): LengthAwarePaginator
    {
        return $this->query()
            ->where('user_id', $userId)
            ->latest()
            ->paginate(20);
    }

    public function recentForUser(int $userId, int $limit = 8): Collection
    {
        return $this->query()
            ->where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function unreadCountForUser(int $userId): int
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('status', NotificationStatus::Unread->value)
            ->count();
    }
}
