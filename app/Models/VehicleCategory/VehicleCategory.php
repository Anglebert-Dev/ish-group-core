<?php

namespace App\Models\VehicleCategory;

use App\Enums\VehicleCategoryStatus;
use App\Libraries\UploadLibrary;
use App\Models\BaseModel;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleCategory extends BaseModel
{
    protected $fillable = [
        'name',
        'description',
        'daily_price',
        'image_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleCategoryStatus::class,
            'daily_price' => 'decimal:2',
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? UploadLibrary::url($this->image_path) : null;
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'vehicle_category_id');
    }

    /**
     * @return list<float>
     */
    public function vehicleDailyAmounts(): array
    {
        $vehicles = $this->relationLoaded('vehicles')
            ? $this->vehicles
            : $this->vehicles()->get(['id', 'daily_price', 'vehicle_category_id']);

        return $vehicles
            ->map(fn (Vehicle $vehicle) => (float) ($vehicle->dailyPrice() ?? 0))
            ->filter(fn (float $amount) => $amount > 0)
            ->values()
            ->all();
    }

    /**
     * @return array{min: float, max: float, count: int}|null
     */
    public function vehicleDailyPriceRange(): ?array
    {
        $amounts = $this->vehicleDailyAmounts();
        if ($amounts === []) {
            return null;
        }

        return [
            'min' => min($amounts),
            'max' => max($amounts),
            'count' => count($amounts),
        ];
    }

    public function fleetDailyPriceLabel(): string
    {
        $range = $this->vehicleDailyPriceRange();
        if ($range === null) {
            return __('body.category_default_daily_price', [
                'amount' => number_format((float) $this->daily_price, 0),
            ]);
        }

        if ($range['min'] === $range['max']) {
            return number_format($range['min'], 0).' RWF/'.__('body.day');
        }

        return __('body.category_daily_price_range', [
            'min' => number_format($range['min'], 0),
            'max' => number_format($range['max'], 0),
        ]);
    }

    public function quoteDailyPrice(): string
    {
        $range = $this->vehicleDailyPriceRange();

        return (string) ($range['min'] ?? $this->daily_price);
    }
}
