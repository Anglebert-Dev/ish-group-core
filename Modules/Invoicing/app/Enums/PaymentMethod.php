<?php

namespace Modules\Invoicing\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case Bank = 'bank';
    case Cheque = 'cheque';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return __('invoicing::invoicing.method_'.$this->value);
    }
}
