<?php

namespace Modules\Inventory\Enums;

enum ItemType: string
{
    case Finished = 'finished';
    case Material = 'material';

    public function label(): string
    {
        return __('inventory::inventory.type_'.$this->value);
    }
}
