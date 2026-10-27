<?php

namespace App\Http\Requests\VehicleMaintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'maintenance_type' => ['required', Rule::in(['service', 'repair', 'oil_change', 'tyres', 'inspection'])],
            'description' => ['nullable', 'string'],
            'date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'min:0'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['scheduled', 'in_progress', 'completed'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
