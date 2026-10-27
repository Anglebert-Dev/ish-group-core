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

    /**
     * Limits that apply to this business: core ones, plus those of modules
     * it can use ("inventory.stores" belongs to Inventory).
     *
     * @return list<string>
     */
    public function keysFor(Business $business): array
    {
        return array_values(array_filter($this->keys(), function (string $key) use ($business) {
            $module = $this->module($key);

            return $module === null || $business->canUseModule($module);
        }));
    }

    /**
     * "Users", or a module's own label ("<module>::<module>.quota_<name>").
     */
    public function label(string $key): string
    {
        $module = $this->module($key);
        $translation = $module === null
            ? 'body.quota_'.$key
            : strtolower($module).'::'.strtolower($module).'.quota_'.substr($key, strlen($module) + 1);

        return trans()->has($translation) ? __($translation) : $key;
    }

    private function module(string $key): ?string
    {
        return str_contains($key, '.') ? strstr($key, '.', true) : null;
    }
}
