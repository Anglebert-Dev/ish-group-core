<?php

namespace App\Enums;

enum CustomerAccountType: string
{
    case Personal = 'personal';
    case Organization = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::Personal => __('body.auth_account_personal'),
            self::Organization => __('body.auth_account_organization'),
        };
    }
}
