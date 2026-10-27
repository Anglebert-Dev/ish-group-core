<?php

namespace Modules\Inventory\Support;

use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\Variant;

/**
 * Turns posted document lines (variant uuid, description, quantity,
 * unit_price) into checked rows for orders, invoices, quotes…
 */
class DocumentLines
{
    /**
     * @return list<array{variant: Variant, description: ?string, quantity: float, unit_price: float, line_total: float}>
     *
     * @throws ValidationException
     */
    public static function resolve(array $rows, string $field = 'lines'): array
    {
        $rows = array_values(array_filter($rows, fn ($row) => is_array($row) && filled($row['variant'] ?? null)));

        if ($rows === []) {
            throw ValidationException::withMessages([$field => __('inventory::inventory.line_none')]);
        }

        $variants = Variant::query()->with('item')
            ->whereIn('uuid', array_column($rows, 'variant'))
            ->get()
            ->keyBy('uuid');

        $lines = [];
        foreach ($rows as $i => $row) {
            $variant = $variants[$row['variant']] ?? null;
            $quantity = round((float) ($row['quantity'] ?? 0), 3);
            $price = round((float) ($row['unit_price'] ?? 0), 2);

            if (! $variant) {
                throw ValidationException::withMessages(["{$field}.{$i}.variant" => __('validation.exists', ['attribute' => __('inventory::inventory.line_item')])]);
            }

            if ($quantity <= 0) {
                throw ValidationException::withMessages(["{$field}.{$i}.quantity" => __('validation.gt.numeric', ['attribute' => __('inventory::inventory.line_quantity'), 'value' => 0])]);
            }

            $lines[] = [
                'variant' => $variant,
                'description' => filled($row['description'] ?? null) ? mb_substr(trim($row['description']), 0, 255) : null,
                'quantity' => $quantity,
                'unit_price' => max(0, $price),
                'line_total' => round($quantity * max(0, $price), 2),
            ];
        }

        return $lines;
    }

    /**
     * Rule set for the raw rows (shape only; resolve() does the checks).
     */
    public static function rules(string $field = 'lines'): array
    {
        return [
            $field => ['required', 'array', 'max:200'],
            "{$field}.*.variant" => ['nullable', 'string', 'max:64'],
            "{$field}.*.description" => ['nullable', 'string', 'max:255'],
            "{$field}.*.quantity" => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            "{$field}.*.unit_price" => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }
}
