<?php

namespace App\Services;

use App\Libraries\GeneralLibrary;

abstract class BaseService
{
    protected function success(mixed $data = null, ?string $message = null): array
    {
        return GeneralLibrary::response(true, $data, $message ?? __('body.success'));
    }

    protected function failure(?string $message = null, mixed $data = null): array
    {
        return GeneralLibrary::response(false, $data, $message ?? __('body.something_went_wrong'));
    }
}
