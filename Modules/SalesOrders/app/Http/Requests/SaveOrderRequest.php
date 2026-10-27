<?php

namespace Modules\SalesOrders\Http\Requests;

use App\Models\Customer\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Support\DocumentLines;

class SaveOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $order = $this->route('order');
        $withLines = ! $order || $order->isEditable();

        return [
            'customer' => ['required', 'string'],
            'store' => ['required', 'string'],
            'order_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'customer_reference' => ['nullable', 'string', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ] + ($withLines ? DocumentLines::rules() : []);
    }

    public function orderData(): array
    {
        $customer = Customer::query()->where('uuid', $this->input('customer'))->first();
        $store = Store::query()->where('uuid', $this->input('store'))->where('is_active', true)->first();

        abort_if(! $customer || ! $store, 422);

        return [
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'order_date' => $this->input('order_date'),
            'due_date' => $this->input('due_date') ?: null,
            'customer_reference' => $this->input('customer_reference') ?: null,
            'discount' => (float) $this->input('discount', 0),
            'notes' => $this->input('notes') ?: null,
        ];
    }

    /**
     * Checked lines, or null when the order's lines are locked.
     */
    public function lines(): ?array
    {
        $order = $this->route('order');

        return ! $order || $order->isEditable() ? DocumentLines::resolve((array) $this->input('lines', [])) : null;
    }
}
