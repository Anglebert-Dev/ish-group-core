<?php

namespace App\Http\Requests\Extra;

use App\Enums\ExtraStatus;
use App\Enums\ExtraType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExtraRequest extends FormRequest
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
            'price' => ['required', 'numeric', 'min:0'],
            'type' => ['required', Rule::enum(ExtraType::class)],
            'status' => ['nullable', Rule::enum(ExtraStatus::class)],
        ];
    }
}
