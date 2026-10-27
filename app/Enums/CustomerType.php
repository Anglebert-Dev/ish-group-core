<?php

namespace App\Enums;

enum CustomerType: string
{
    case Person = 'person';
    case Company = 'company';

    public function label(): string
    {
        return __('body.customer_type_'.$this->value);
    }
}
