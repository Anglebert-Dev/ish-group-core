<?php

namespace App\Http\Requests\VehicleOwner;

use App\Enums\VehicleOwnerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('vehicle_owners', 'email')],
            'address' => ['nullable', 'string'],
            'national_id' => ['required', 'string', 'max:50', Rule::unique('vehicle_owners', 'national_id')],
            'notes' => ['nullable', 'string'],
            'owner_share_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'company_share_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'agreement_starts_on' => ['nullable', 'date'],
            'agreement_ends_on' => ['nullable', 'date', 'after_or_equal:agreement_starts_on'],
            'status' => ['nullable', Rule::enum(VehicleOwnerStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $owner = (float) $this->input('owner_share_percent');
            $company = (float) $this->input('company_share_percent');

            if (abs(($owner + $company) - 100) > 0.01) {
                $validator->errors()->add('company_share_percent', __('body.income_split_must_total_100'));
            }
        });
    }
}
