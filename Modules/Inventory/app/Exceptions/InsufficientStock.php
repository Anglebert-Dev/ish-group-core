<?php

namespace Modules\Inventory\Exceptions;

use RuntimeException;

/**
 * A movement would take stock below zero. Nothing was saved.
 */
class InsufficientStock extends RuntimeException
{
    public function __construct(
        public readonly string $itemName,
        public readonly string $storeName,
        public readonly float $available,
        public readonly float $requested,
    ) {
        parent::__construct(__('inventory::inventory.insufficient_stock', [
            'item' => $itemName,
            'store' => $storeName,
            'available' => self::format($available),
            'requested' => self::format($requested),
        ]));
    }

    public static function format(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ','), '0'), '.');
    }
}
