<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\CouponDuration;
use Modules\Billing\Enums\CouponType;

class SaveCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $coupon = $this->route('coupon');

        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('billing_coupons', 'code')->ignore($coupon?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'numeric', 'min:0.01', $this->input('type') === 'percent' ? 'max:100' : 'max:999999999'],
            'duration' => ['required', Rule::enum(CouponDuration::class)],
            'duration_periods' => ['nullable', 'required_if:duration,repeating', 'integer', 'min:1', 'max:120'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'package_ids' => ['nullable', 'array'],
            'package_ids.*' => ['integer', 'exists:billing_packages,id'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    public function couponData(): array
    {
        $data = $this->validated();

        return [
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'value' => $data['value'],
            'duration' => $data['duration'],
            'duration_periods' => $data['duration'] === 'repeating' ? (int) $data['duration_periods'] : null,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'package_ids' => array_values(array_map('intval', $data['package_ids'] ?? [])) ?: null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
