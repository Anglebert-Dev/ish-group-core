<?php

namespace App\Support\Settings;

use App\Enums\ActivityLogAction;
use App\Models\Business\Business;
use App\Models\Business\BusinessSetting;
use App\Support\Tenancy\CurrentBusiness;

/**
 * Key/value settings per business, for the core and every module.
 * Keys are namespaced by module: "inventory.units", "numbering.sales_order.prefix".
 *
 *   app(BusinessSettings::class)->get('inventory.units', ['pcs']);
 *   app(BusinessSettings::class)->set('inventory.units', ['pcs', 'm']);
 *
 * Defaults stay in code (the $default argument); only changes are stored.
 * Without a $business the current one is used.
 */
class BusinessSettings
{
    /** @var array<int, array<string, mixed>> */
    private array $loaded = [];

    public function __construct(protected CurrentBusiness $current)
    {
    }

    public function get(string $key, mixed $default = null, ?Business $business = null): mixed
    {
        $values = $this->all($business);

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    /**
     * Every stored setting under a prefix, e.g. group('inventory') → ['inventory.units' => ...].
     *
     * @return array<string, mixed>
     */
    public function group(string $prefix, ?Business $business = null): array
    {
        return array_filter(
            $this->all($business),
            fn (string $key) => str_starts_with($key, rtrim($prefix, '.').'.'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function set(string $key, mixed $value, ?Business $business = null): void
    {
        $businessId = $this->businessId($business);
        $old = $this->get($key, null, $business);

        if ($old === $value && array_key_exists($key, $this->all($business))) {
            return;
        }

        BusinessSetting::query()->updateOrCreate(['business_id' => $businessId, 'key' => $key], ['value' => $value]);
        $this->loaded[$businessId][$key] = $value;

        audit(ActivityLogAction::Updated, __('body.audit_log_business_setting', ['key' => $key]), null, [
            'key' => $key, 'old' => $old, 'new' => $value,
        ], $businessId);
    }

    /**
     * Back to the default.
     */
    public function forget(string $key, ?Business $business = null): void
    {
        $businessId = $this->businessId($business);

        if (BusinessSetting::query()->where('business_id', $businessId)->where('key', $key)->delete()) {
            unset($this->loaded[$businessId][$key]);
            audit(ActivityLogAction::Updated, __('body.audit_log_business_setting_reset', ['key' => $key]), null, ['key' => $key], $businessId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function all(?Business $business): array
    {
        $businessId = $this->businessId($business);

        return $this->loaded[$businessId] ??= BusinessSetting::query()
            ->where('business_id', $businessId)
            ->pluck('value', 'key')
            ->all();
    }

    private function businessId(?Business $business): int
    {
        return $business?->id ?? $this->current->id()
            ?? throw new \LogicException('BusinessSettings needs a business.');
    }
}
