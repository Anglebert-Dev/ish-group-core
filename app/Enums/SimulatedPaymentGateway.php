<?php

namespace App\Enums;

enum SimulatedPaymentGateway: string
{
    case Card = 'card';
    case MoMo = 'momo';

    public function label(): string
    {
        return match ($this) {
            self::Card => __('body.pay_with_card'),
            self::MoMo => __('body.pay_with_momo'),
        };
    }
}
