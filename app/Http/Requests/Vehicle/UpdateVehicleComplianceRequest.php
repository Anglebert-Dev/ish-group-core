<?php

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleComplianceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'insurance_company' => ['nullable', 'string', 'max:255'],
            'insurance_expires_at' => ['nullable', 'date'],
            'insurance_document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'inspection_done_at' => ['nullable', 'date'],
            'inspection_expires_at' => ['nullable', 'date', 'after_or_equal:inspection_done_at'],
            'inspection_document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'carte_jaune_number' => ['nullable', 'string', 'max:100'],
            'carte_jaune_issued_at' => ['nullable', 'date'],
            'carte_jaune_document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }
}
