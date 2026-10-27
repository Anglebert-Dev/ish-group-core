<?php

namespace App\Http\Requests\AccountAssignment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chart_of_account_id' => ['required', 'exists:chart_of_accounts,id'],
        ];
    }
}
