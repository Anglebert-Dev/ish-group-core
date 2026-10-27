<?php

namespace App\Http\Requests\Rental;

use App\Enums\FuelLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPickupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mileage' => ['required', 'integer', 'min:0'],
            'fuel_level' => ['required', Rule::enum(FuelLevel::class)],
            'condition_notes' => ['nullable', 'string'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:4096'],
        ];
    }
}
