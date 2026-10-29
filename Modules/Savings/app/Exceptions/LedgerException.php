<?php

namespace Modules\Savings\Exceptions;

use RuntimeException;

/**
 * The transaction breaks a ledger rule; nothing was posted.
 */
class LedgerException extends RuntimeException
{
}
