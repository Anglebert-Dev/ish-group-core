<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Inventory\Enums\ItemType;
use Modules\Inventory\Services\ItemService;

class SaveItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        $quantity = ['nullable', 'numeric', 'min:0', 'max:999999999'];

        return [
            'type' => ['required', Rule::enum(ItemType::class)],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:60'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', Rule::in(app(ItemService::class)->units())],
            'description' => ['nullable', 'string', 'max:5000'],
            'reorder_level' => $quantity,
            'option1_name' => ['nullable', 'string', 'max:40'],
            'option2_name' => ['nullable', 'string', 'max:40'],
            'variants' => ['nullable', 'array', 'max:300'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.option1' => ['nullable', 'string', 'max:60'],
            'variants.*.option2' => ['nullable', 'string', 'max:60'],
            'variants.*.sku' => ['nullable', 'string', 'max:60'],
            'variants.*.cost_price' => $money,
            'variants.*.sale_price' => $money,
            'variants.*.reorder_level' => $quantity,
            'variants.*.opening' => $quantity,
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! filled($this->input('option1_name')) && ! filled($this->input('option2_name'))) {
                return;
            }

            $rows = collect($this->input('variants', []))
                ->filter(fn ($row) => is_array($row) && (filled($row['option1'] ?? null) || filled($row['option2'] ?? null)));

            if ($rows->isEmpty()) {
                $validator->errors()->add('variants', __('inventory::inventory.item_variant_required'));

                return;
            }

            $keys = $rows->map(fn ($row) => mb_strtolower(trim((string) ($row['option1'] ?? ''))).'|'.mb_strtolower(trim((string) ($row['option2'] ?? ''))));

            if ($keys->count() !== $keys->unique()->count()) {
                $validator->errors()->add('variants', __('inventory::inventory.variant_duplicate'));
            }
        }];
    }

    public function itemData(): array
    {
        return collect($this->validated())->only([
            'type', 'name', 'sku', 'category', 'unit', 'description', 'reorder_level', 'option1_name', 'option2_name',
        ])->map(fn ($value) => $value === '' ? null : $value)->all();
    }

    public function variantRows(): array
    {
        return array_values($this->validated('variants') ?? []);
    }
}
