<?php

namespace App\Http\Requests\VehicleCategory;

use App\Enums\VehicleCategoryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleCategoryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(VehicleCategoryStatus::class)],
        ];
    }
}
