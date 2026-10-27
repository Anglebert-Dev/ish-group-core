<?php

namespace App\Http\Requests\Booking;

use App\Enums\SimulatedPaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public checkout — customer chooses Card or MoMo (simulated for now).
 * A real gateway (IremboPay / Flutterwave / DPO) can replace this later.
 */
class ConfirmBookingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_gateway' => ['required', Rule::enum(SimulatedPaymentGateway::class)],
        ];
    }
}
