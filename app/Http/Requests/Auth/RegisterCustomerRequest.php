<?php

namespace App\Http\Requests\Auth;

use App\Enums\CustomerAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_type' => ['required', Rule::enum(CustomerAccountType::class)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['required_if:account_type,'.CustomerAccountType::Organization->value, 'nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:9'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function attributes(): array
    {
        return [
            'account_type' => __('body.auth_account_type'),
            'company_name' => __('body.company_name'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }
}
