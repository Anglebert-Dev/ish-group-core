<?php

namespace App\Http\Requests\ChartOfAccount;

use App\Enums\AccountType;
use App\Enums\ChartOfAccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:chart_of_accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'is_posting' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::enum(ChartOfAccountStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
