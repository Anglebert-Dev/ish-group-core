<?php

namespace App\Http\Requests\Booking;

use App\Libraries\GeneralLibrary;
use Illuminate\Foundation\Http\FormRequest;

class CheckBookingAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pickup_station_id' => ['required', 'integer', 'exists:stations,id'],
            'vehicle_category_id' => ['required', 'integer', 'exists:vehicle_categories,id'],
            'pickup_at' => ['required', 'date', 'after_or_equal:'.GeneralLibrary::earliestPickupAt()->toDateTimeString()],
            'return_at' => ['required', 'date', 'after:pickup_at'],
        ];
    }
}
