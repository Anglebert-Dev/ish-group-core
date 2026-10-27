<?php

namespace App\Http\Requests\CorporateCustomer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCorporateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'tin' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'string'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'contacts.primary.name' => ['nullable', 'string', 'max:255'],
            'contacts.primary.email' => ['nullable', 'email', 'max:255'],
            'contacts.primary.phone' => ['nullable', 'string', 'max:30'],
            'contacts.booking.name' => ['nullable', 'string', 'max:255'],
            'contacts.booking.email' => ['nullable', 'email', 'max:255'],
            'contacts.booking.phone' => ['nullable', 'string', 'max:30'],
            'contacts.finance.name' => ['nullable', 'string', 'max:255'],
            'contacts.finance.email' => ['nullable', 'email', 'max:255'],
            'contacts.finance.phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
