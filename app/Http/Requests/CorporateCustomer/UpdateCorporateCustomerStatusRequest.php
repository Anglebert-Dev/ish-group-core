<?php

namespace App\Http\Requests\CorporateCustomer;

use App\Enums\CorporateCustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCorporateCustomerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CorporateCustomerStatus::class)],
        ];
    }
}
