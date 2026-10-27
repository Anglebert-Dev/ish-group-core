<?php

namespace Modules\Inventory\Enums;

/**
 * Why a quantity changed. Modules use their own types (Sale, ProductionIn…)
 * so reports can tell stock movements apart.
 */
enum MovementType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Adjustment = 'adjustment';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case ProductionIn = 'production_in';
    case ProductionOut = 'production_out';
    case Purchase = 'purchase';

    public function label(): string
    {
        return __('inventory::inventory.movement_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Adjustment => 'warning',
            self::TransferOut, self::TransferIn => 'muted',
            self::Issue, self::Sale, self::ProductionOut => 'danger',
            default => 'success',
        };
    }
}
