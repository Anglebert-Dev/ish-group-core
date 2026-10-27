<?php

namespace App\Http\Requests\VehicleOwner;

use Illuminate\Validation\Rule;

class UpdateVehicleOwnerRequest extends StoreVehicleOwnerRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $owner = $this->route('owner');

        $rules['email'] = ['nullable', 'email', 'max:255', Rule::unique('vehicle_owners', 'email')->ignore($owner)];
        $rules['national_id'] = ['required', 'string', 'max:50', Rule::unique('vehicle_owners', 'national_id')->ignore($owner)];

        return $rules;
    }
}
