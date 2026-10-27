<?php

namespace App\Http\Requests\ChartOfAccount;

use App\Enums\ChartOfAccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChartOfAccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ChartOfAccountStatus::class)],
        ];
    }
}
