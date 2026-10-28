<?php

namespace Modules\SalesOrders\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProduction = 'in_production';
    case Ready = 'ready';
    case PartlyDelivered = 'partly_delivered';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('salesorders::orders.status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'muted',
            self::Confirmed, self::InProduction => 'brand',
            self::Ready, self::PartlyDelivered => 'warning',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Stock is promised to the customer but not yet taken out.
     */
    public function reservesStock(): bool
    {
        return in_array($this, [self::Confirmed, self::InProduction, self::Ready, self::PartlyDelivered], true);
    }

    /**
     * Statuses staff can move a confirmed order between by hand.
     *
     * @return list<self>
     */
    public static function progress(): array
    {
        return [self::Confirmed, self::InProduction, self::Ready];
    }
}
