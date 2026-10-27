<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function existsAny(): bool
    {
        return $this->query()->withTrashed()->exists();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }

    /**
     * Users are not globally scoped (see BelongsToBusiness), so every
     * tenant-facing query goes through here.
     */
    public function forBusiness(?int $businessId): Builder
    {
        return $this->query()->where('business_id', $businessId);
    }
}
