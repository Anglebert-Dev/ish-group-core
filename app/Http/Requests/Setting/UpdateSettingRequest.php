<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'brand_color' => ['required', 'string', 'regex:/^#[0-9A-F]{6}$/'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'favicon' => ['nullable', 'image', 'max:1024'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('brand_color')) {
            $this->merge([
                'brand_color' => strtoupper((string) $this->input('brand_color')),
            ]);
        }
    }
}
