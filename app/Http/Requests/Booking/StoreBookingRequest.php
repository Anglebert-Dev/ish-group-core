<?php

namespace App\Http\Requests\Booking;

use App\Enums\LicenceCategory;
use App\Enums\PaymentOption;
use App\Enums\PickupMethod;
use App\Enums\RentalType;
use App\Enums\ReturnMethod;
use App\Enums\StationStatus;
use App\Libraries\GeneralLibrary;
use App\Models\Station\Station;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pickupMethod = $this->input('pickup_method', PickupMethod::Station->value);
        $returnMethod = $this->input('return_method', ReturnMethod::Station->value);

        $fallbackStation = Station::query()
            ->where('status', StationStatus::Active->value)
            ->orderBy('name')
            ->value('id');

        $pickupStationId = $this->input('pickup_station_id');
        $returnStationId = $this->input('return_station_id');

        if ($pickupMethod === PickupMethod::Delivery->value) {
            $pickupStationId = $pickupStationId ?: $returnStationId ?: $fallbackStation;
        }

        if ($returnMethod === ReturnMethod::Collection->value) {
            $returnStationId = $returnStationId ?: $pickupStationId ?: $fallbackStation;
        }

        $this->merge([
            'pickup_method' => $pickupMethod,
            'return_method' => $returnMethod,
            'pickup_station_id' => $pickupStationId,
            'return_station_id' => $returnStationId,
            'delivery_address' => $pickupMethod === PickupMethod::Delivery->value
                ? $this->input('delivery_address')
                : null,
            'collection_address' => $returnMethod === ReturnMethod::Collection->value
                ? $this->input('collection_address')
                : null,
        ]);
    }

    public function rules(): array
    {
        $earliest = GeneralLibrary::earliestPickupAt()->toDateTimeString();
        $isDelivery = $this->input('pickup_method') === PickupMethod::Delivery->value;
        $isCollection = $this->input('return_method') === ReturnMethod::Collection->value;

        return [
            'customer_id' => ['required_without:corporate_customer_id', 'nullable', 'exists:customers,id', 'prohibits:corporate_customer_id'],
            'corporate_customer_id' => ['required_without:customer_id', 'nullable', 'exists:corporate_customers,id'],

            'pickup_method' => ['required', Rule::enum(PickupMethod::class)],
            'return_method' => ['required', Rule::enum(ReturnMethod::class)],
            'pickup_station_id' => [
                Rule::requiredIf(! $isDelivery),
                'nullable',
                'integer',
                Rule::exists('stations', 'id')->where(fn ($q) => $q->where('status', StationStatus::Active->value)->whereNull('deleted_at')),
            ],
            'return_station_id' => [
                Rule::requiredIf(! $isCollection),
                'nullable',
                'integer',
                Rule::exists('stations', 'id')->where(fn ($q) => $q->where('status', StationStatus::Active->value)->whereNull('deleted_at')),
            ],
            'delivery_address' => [$isDelivery ? 'required' : 'nullable', 'string', 'max:500'],
            'collection_address' => [$isCollection ? 'required' : 'nullable', 'string', 'max:500'],

            'pickup_at' => ['required', 'date', 'after_or_equal:'.$earliest],
            'return_at' => ['required', 'date', 'after:pickup_at'],

            'vehicle_category_id' => ['required', 'exists:vehicle_categories,id'],

            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'driver_license_number' => ['required_if:rental_type,self_drive', 'nullable', 'string', 'max:100'],
            'driver_license_category' => ['required_if:rental_type,self_drive', 'nullable', Rule::enum(LicenceCategory::class)],

            'payment_option' => [
                'required',
                Rule::enum(PaymentOption::class),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $option = is_string($value) ? PaymentOption::tryFrom($value) : null;
                    $isCorporate = filled($this->input('corporate_customer_id'));

                    if ($option === PaymentOption::CorporateBilling && ! $isCorporate) {
                        $fail(__('body.corporate_billing_requires_corporate'));
                    }
                },
            ],
            'notes' => ['nullable', 'string'],

            'extras' => ['nullable', 'array'],
            'extras.*' => ['nullable', 'integer', 'min:0', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'pickup_at.after_or_equal' => __('body.pickup_must_be_1_hour_ahead'),
            'delivery_address.required' => __('body.delivery_address_required'),
            'collection_address.required' => __('body.collection_address_required'),
            'driver_license_number.required_if' => __('body.license_number_required_self_drive'),
            'driver_license_category.required_if' => __('body.license_category_required_self_drive'),
        ];
    }
}
