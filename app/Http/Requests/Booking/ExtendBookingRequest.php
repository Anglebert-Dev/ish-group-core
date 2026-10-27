<?php

namespace App\Http\Requests\Booking;

use App\Libraries\GeneralLibrary;
use Illuminate\Foundation\Http\FormRequest;

class ExtendBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_return_at' => ['required', 'date', 'after:'.now()->toDateTimeString()],
        ];
    }

    public function messages(): array
    {
        return [
            'new_return_at.after' => __('body.extension_must_be_in_future'),
        ];
    }
}
