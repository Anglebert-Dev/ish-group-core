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

    private ?Business $loaded = null;

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

        if (! $id) {
            return null;
        }

        // Memoised for this request only (views ask for branding many times).
        if ($this->loaded?->id !== $id) {
            $this->loaded = Business::query()->find($id);
        }

        return $this->loaded;
    }

    public function set(Business|int|null $business): void
    {
        $this->pinnedId = $business instanceof Business ? $business->id : $business;
        $this->loaded = $business instanceof Business ? $business : null;
    }

    public function forget(): void
    {
        $this->loaded = null;
    }
}
