<?php

namespace App\Repositories\ActivityLog;

use App\Models\ActivityLog\ActivityLog;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityLogRepository extends BaseRepository
{
    public function __construct(ActivityLog $model)
    {
        parent::__construct($model);
    }

    public function paginateRecent(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with('user')
            ->latest()
            ->paginate($perPage);
    }
}
