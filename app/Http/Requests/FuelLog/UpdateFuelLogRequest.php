<?php

namespace App\Http\Requests\FuelLog;

use App\Enums\FuelLogStatus;
use App\Enums\FuelType;
use App\Models\Vehicle\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFuelLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('fuel_type') || ! $this->filled('vehicle_id')) {
            return;
        }

        $vehicle = Vehicle::query()->find($this->input('vehicle_id'));

        if ($vehicle?->fuel_type) {
            $this->merge(['fuel_type' => $vehicle->fuel_type->value]);
        }
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'gas_station_name' => ['required', 'string', 'max:255'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'fuel_type' => ['required', Rule::enum(FuelType::class)],
            'date' => ['required', 'date'],
            'liters' => ['required', 'numeric', 'min:0.01'],
            'cost' => ['required', 'numeric', 'min:0'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::enum(FuelLogStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
