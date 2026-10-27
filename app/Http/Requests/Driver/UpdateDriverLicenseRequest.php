<?php

namespace App\Http\Requests\Driver;

use App\Enums\LicenceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $driver = $this->route('driver');

        return [
            'national_id' => ['required', 'string', 'max:50', Rule::unique('drivers', 'national_id')->ignore($driver)],
            'license_number' => ['required', 'string', 'max:100'],
            'license_expiry' => ['required', 'date'],
            'license_category' => ['nullable', Rule::enum(LicenceCategory::class)],
            'license_document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }
}
