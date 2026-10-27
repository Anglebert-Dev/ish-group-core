<?php

namespace Modules\Billing\Models;

use App\Models\BaseModel;
use App\Models\Business\Business;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Support\Money;

class Package extends BaseModel
{
    protected $table = 'billing_packages';

    protected $fillable = [
        'name', 'description', 'price', 'interval', 'interval_count', 'trial_days',
        'modules', 'limits', 'features', 'is_active', 'is_private', 'business_ids',
        'once_per_business', 'is_popular', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'interval' => BillingInterval::class,
            'interval_count' => 'integer',
            'trial_days' => 'integer',
            'modules' => 'array',
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_private' => 'boolean',
            'business_ids' => 'array',
            'once_per_business' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    /**
     * May this business pick the package itself? (active, not private, and
     * either open to everyone or listed for this business)
     */
    public function isAvailableTo(Business $business): bool
    {
        return $this->is_active && ! $this->is_private
            && (empty($this->business_ids) || in_array($business->id, array_map('intval', $this->business_ids), true));
    }

    public function isFree(): bool
    {
        return Money::toCents($this->price) === 0;
    }

    public function includesModule(string $module): bool
    {
        return in_array(strtolower($module), array_map('strtolower', $this->modules ?? []), true);
    }

    public function limit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null || (int) $value <= 0 ? null : (int) $value;
    }

    /** e.g. "every 3 months" */
    public function intervalLabel(): string
    {
        return $this->interval->label($this->interval_count);
    }

    /** Price per day, to tell an upgrade from a downgrade across intervals. */
    public function dailyPrice(): float
    {
        return Money::toCents($this->price) / max(1, $this->interval->days() * $this->interval_count);
    }

    /**
     * Frozen copy stored on the subscription.
     */
    public function snapshot(): array
    {
        return [
            'package_id' => $this->id,
            'name' => $this->name,
            'price' => Money::format2($this->price),
            'interval' => $this->interval->value,
            'interval_count' => $this->interval_count,
            'modules' => array_values($this->modules ?? []),
            'limits' => $this->limits ?? [],
        ];
    }
}
