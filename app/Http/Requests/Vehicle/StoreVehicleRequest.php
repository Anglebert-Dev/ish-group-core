<?php

namespace App\Http\Requests\Vehicle;

use App\Enums\FuelType;
use App\Enums\LicenceCategory;
use App\Enums\TransmissionType;
use App\Enums\VehicleColor;
use App\Enums\VehicleOwnershipType;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Libraries\RwandaPlateLibrary;
use App\Libraries\VehicleCatalogLibrary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('ownership_type') === VehicleOwnershipType::Company->value) {
            $this->merge(['vehicle_owner_id' => null]);
        }

        $normalized = RwandaPlateLibrary::normalize($this->input('plate_number'));
        if ($normalized !== null) {
            $this->merge(['plate_number' => $normalized]);
        } elseif ($this->has('plate_number')) {
            $this->merge(['plate_number' => strtoupper(trim((string) $this->input('plate_number')))]);
        }

        if ($this->input('color') === '') {
            $this->merge(['color' => null]);
        }
    }

    public function rules(): array
    {
        $make = (string) $this->input('make');

        return [
            'make' => ['required', 'string', 'max:100', Rule::in(VehicleCatalogLibrary::makes())],
            'model' => ['required', 'string', 'max:100', Rule::in(VehicleCatalogLibrary::modelsFor($make))],
            'year' => ['required', 'integer', Rule::in(VehicleCatalogLibrary::years())],
            'plate_number' => [
                'required',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! RwandaPlateLibrary::isValid(is_string($value) ? $value : null)) {
                        $fail(__('body.plate_number_invalid'));
                    }
                },
                'unique:vehicles,plate_number',
            ],
            'vin' => ['nullable', 'string', 'max:50', 'unique:vehicles,vin'],
            'color' => ['nullable', Rule::enum(VehicleColor::class)],

            'vehicle_category_id' => ['required', 'exists:vehicle_categories,id'],
            'daily_price' => ['required', 'numeric', 'min:0'],
            'station_id' => ['required', 'exists:stations,id'],
            'ownership_type' => ['required', Rule::enum(VehicleOwnershipType::class)],
            'vehicle_owner_id' => [
                Rule::requiredIf(fn () => $this->input('ownership_type') === VehicleOwnershipType::Outsider->value),
                'nullable',
                'exists:vehicle_owners,id',
            ],

            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'seats' => ['required', 'integer', 'min:1', 'max:100'],
            'transmission' => ['required', Rule::enum(TransmissionType::class)],
            'fuel_type' => ['required', Rule::enum(FuelType::class)],
            'engine_capacity' => ['nullable', 'string', 'max:20'],
            'weight' => ['nullable', 'numeric', 'min:0'],

            'mileage' => ['nullable', 'integer', 'min:0'],
            'required_license_category' => ['nullable', Rule::enum(LicenceCategory::class)],
            'status' => ['nullable', Rule::enum(VehicleStatus::class)],
            'notes' => ['nullable', 'string'],

            'insurance_company' => ['nullable', 'string', 'max:255'],
            'insurance_expires_at' => ['nullable', 'date'],
            'insurance_document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'inspection_done_at' => ['nullable', 'date'],
            'inspection_expires_at' => ['nullable', 'date', 'after_or_equal:inspection_done_at'],
            'inspection_document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'carte_jaune_number' => ['nullable', 'string', 'max:100'],
            'carte_jaune_issued_at' => ['nullable', 'date'],
            'carte_jaune_document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],

            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:4096'],
        ];
    }
}
