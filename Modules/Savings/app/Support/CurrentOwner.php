<?php

namespace Modules\Savings\Support;

/**
 * Whose finances we are looking at: the signed-in user, or a user set
 * explicitly (daily alerts, seeders). Finances are private per user.
 */
class CurrentOwner
{
    private ?int $id = null;

    public function id(): ?int
    {
        return $this->id ?? auth('staff')->id();
    }

    public function set(?int $userId): void
    {
        $this->id = $userId;
    }
}
