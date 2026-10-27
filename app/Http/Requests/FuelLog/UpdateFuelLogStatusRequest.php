<?php

namespace App\Http\Requests\FuelLog;

use App\Enums\FuelLogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFuelLogStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(FuelLogStatus::class)],
        ];
    }
}
