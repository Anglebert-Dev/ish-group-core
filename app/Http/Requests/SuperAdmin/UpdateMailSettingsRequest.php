<?php

namespace App\Http\Requests\SuperAdmin;

use App\Services\Install\InstallRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return InstallRules::mail() + [
            'action' => ['required', 'in:test,save'],
            'test_to' => ['required_if:action,test', 'nullable', 'email', 'max:255'],
        ];
    }
}
