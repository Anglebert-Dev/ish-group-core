<?php

namespace App\Http\Requests\Rental;

use App\Enums\FuelLevel;
use App\Models\Booking\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $minMileage = $this->pickupMileage();

        return [
            'mileage' => ['required', 'integer', 'min:'.$minMileage],
            'fuel_level' => ['required', Rule::enum(FuelLevel::class)],
            'condition_notes' => ['nullable', 'string'],
            'damage_charges' => ['nullable', 'numeric', 'min:0'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        $minMileage = $this->pickupMileage();

        return [
            'mileage.min' => __('body.return_mileage_must_be_at_least_pickup', [
                'mileage' => number_format($minMileage),
            ]),
        ];
    }

    protected function pickupMileage(): int
    {
        /** @var Booking|null $booking */
        $booking = $this->route('booking');

        if (! $booking instanceof Booking) {
            return 0;
        }

        $booking->loadMissing('pickupInspection');

        return (int) ($booking->pickupInspection?->mileage ?? 0);
    }
}
