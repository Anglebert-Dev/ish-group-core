<?php

namespace App\Support\Entitlements;

use App\Contracts\Entitlements;
use App\Enums\UserStatus;
use App\Models\Business\Business;
use App\Models\User;
use Closure;

/**
 * Countable limits ("users", or keys modules register). Counters say how
 * many a business currently has; the active Entitlements say how many it may.
 *
 *   Quota::register('website.customers', fn (Business $b) => ...count...);
 *   app(Quota::class)->canAdd($business, 'users');
 */
class Quota
{
    /** @var array<string, Closure(Business): int> */
    private array $counters = [];

    public function __construct()
    {
        $this->register('users', fn (Business $business) => User::query()
            ->where('business_id', $business->id)
            ->where('status', UserStatus::Active->value)
            ->count());
    }

    public function register(string $key, Closure $counter): void
    {
        $this->counters[$key] = $counter;
    }

    public function used(Business $business, string $key): int
    {
        return isset($this->counters[$key]) ? (int) ($this->counters[$key])($business) : 0;
    }

    public function limit(Business $business, string $key): ?int
    {
        $limit = app(Entitlements::class)->limit($business, $key);

        return $limit === null || $limit <= 0 ? null : $limit;
    }

    /**
     * Room for $adding more?
     */
    public function canAdd(Business $business, string $key, int $adding = 1): bool
    {
        $limit = $this->limit($business, $key);

        return $limit === null || $this->used($business, $key) + $adding <= $limit;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->counters);
    }
}
