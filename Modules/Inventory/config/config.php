<?php

/*
|--------------------------------------------------------------------------
| Inventory module
|--------------------------------------------------------------------------
| Defaults only. Each business can change its units in Inventory → Settings
| (stored with BusinessSettings as "inventory.units").
*/

return [
    'name' => 'Inventory',

    'units' => ['pcs', 'pair', 'dozen', 'm', 'yard', 'roll', 'kg', 'g', 'l', 'box', 'set'],

    'default_store_name' => 'Main store',
];
