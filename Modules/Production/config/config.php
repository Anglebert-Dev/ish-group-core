<?php

return [
    'name' => 'Production',

    'prefix' => 'WO',

    // Default stages; each business sets its own in Production → Settings
    // ("production.stages"). The last stage is "finished": it adds stock.
    'stages' => ['Cutting', 'Sewing', 'Finishing', 'Packing'],
];
