<?php

namespace App\Support\Tenancy;

use App\Models\Business\Business;

/**
 * Resolves the tenant for the current request. Re-read from the signed-in
 * staff user every time (never cached in the session) so a change made by a
 * superadmin is seen on the very next request.
 *
 * Contexts without a staff user (console, public website, queued jobs) can
 * pin a business with set().
 */
class CurrentBusiness
{
    private ?int $pinnedId = null;

    public function id(): ?int
    {
        if ($this->pinnedId !== null) {
            return $this->pinnedId;
        }

        return auth('staff')->user()?->business_id;
    }

    public function get(): ?Business
    {
        $id = $this->id();

        return $id ? Business::query()->find($id) : null;
    }

    public function set(Business|int|null $business): void
    {
        $this->pinnedId = $business instanceof Business ? $business->id : $business;
    }
}
