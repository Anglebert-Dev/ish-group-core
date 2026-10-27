<?php

namespace Modules\Billing\Models;

use App\Models\BaseModel;
use App\Models\Business\Business;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\SubscriptionStatus;

class Subscription extends BaseModel
{
    protected $table = 'billing_subscriptions';

    protected $fillable = [
        'business_id', 'package_id', 'status', 'snapshot', 'trial_ends_at',
        'current_period_start', 'current_period_end', 'grace_ends_at',
        'cancel_at_period_end', 'canceled_at', 'ended_at', 'scheduled_package_id',
        'coupon_id', 'coupon_periods_remaining', 'last_reminder_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'snapshot' => 'array',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'coupon_periods_remaining' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class)->withTrashed();
    }

    public function scheduledPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'scheduled_package_id')->withTrashed();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class)->withTrashed();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('id');
    }

    public function name(): string
    {
        return (string) ($this->snapshot['name'] ?? $this->package?->name ?? '—');
    }

    public function price(): string
    {
        return (string) ($this->snapshot['price'] ?? '0.00');
    }

    public function interval(): BillingInterval
    {
        return BillingInterval::from($this->snapshot['interval'] ?? 'month');
    }

    public function intervalCount(): int
    {
        return (int) ($this->snapshot['interval_count'] ?? 1);
    }

    public function includesModule(string $module): bool
    {
        return in_array(strtolower($module), array_map('strtolower', $this->snapshot['modules'] ?? []), true);
    }

    public function limit(string $key): ?int
    {
        $value = $this->snapshot['limits'][$key] ?? null;

        return $value === null || (int) $value <= 0 ? null : (int) $value;
    }

    public function isCurrent(): bool
    {
        return in_array($this->status, SubscriptionStatus::current(), true);
    }
}
