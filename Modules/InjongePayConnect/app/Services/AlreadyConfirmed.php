<?php

namespace Modules\InjongePayConnect\Services;

use RuntimeException;

class AlreadyConfirmed extends RuntimeException
{
    public function __construct(public readonly ?string $invoiceNo)
    {
        parent::__construct(__('injongepayconnect::connect.err_already_confirmed'));
    }
}
