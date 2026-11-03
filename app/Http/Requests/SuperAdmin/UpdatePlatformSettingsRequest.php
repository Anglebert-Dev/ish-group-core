<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allow_registration' => ['boolean'],
            'default_business_active_status' => ['boolean'],
            'allow_custom_css' => ['boolean'],
            'business_manages_staff' => ['boolean'],
            'idle_timeout_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'tab' => ['nullable', 'in:registration,security'],
        ];
    }
}
