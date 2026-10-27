<?php

namespace App\Http\Requests\VehicleCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'daily_price' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
