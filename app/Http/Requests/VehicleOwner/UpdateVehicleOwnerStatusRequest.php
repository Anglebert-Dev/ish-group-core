<?php

namespace App\Http\Requests\VehicleOwner;

use App\Enums\VehicleOwnerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleOwnerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(VehicleOwnerStatus::class)],
        ];
    }
}
