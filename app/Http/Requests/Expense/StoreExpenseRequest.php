<?php

namespace App\Http\Requests\Expense;

use App\Enums\ExpenseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'chart_of_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'paid' => ['nullable', 'boolean'],
            'post_now' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(ExpenseStatus::class)],
        ];
    }
}
