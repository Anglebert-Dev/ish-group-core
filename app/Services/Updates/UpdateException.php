<?php

namespace App\Services\Updates;

use RuntimeException;

/** Anything that stops an update check or install; the message is shown to the admin. */
class UpdateException extends RuntimeException
{
}
