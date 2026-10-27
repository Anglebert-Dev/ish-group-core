<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $driver = $this->route('driver');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'national_id' => ['required', 'string', 'max:50', Rule::unique('drivers', 'national_id')->ignore($driver)],
            'license_number' => ['required', 'string', 'max:100'],
            'license_expiry' => ['required', 'date'],
            'license_category' => ['nullable', Rule::enum(\App\Enums\LicenceCategory::class)],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'employment_status' => ['nullable', Rule::in(['employed', 'contractor'])],
            'availability_status' => ['nullable', Rule::in(['available', 'on_job', 'off_duty'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],

            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }
}
