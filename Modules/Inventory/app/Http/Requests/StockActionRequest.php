<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->input('action');

        return [
            'action' => ['required', Rule::in(['receive', 'issue', 'adjust', 'transfer'])],
            'variant' => ['required', 'string'],
            'store' => ['required', 'string'],
            'to_store' => [Rule::requiredIf($action === 'transfer'), 'nullable', 'string', 'different:store'],
            'quantity' => ['required', 'numeric', $action === 'adjust' ? 'min:0' : 'gt:0', 'max:999999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'reason' => [Rule::requiredIf(in_array($action, ['adjust', 'issue'], true)), 'nullable', 'string', 'max:500'],
        ];
    }
}
