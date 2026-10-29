<?php

namespace Modules\Savings\Exceptions;

use Illuminate\Support\Collection;

/**
 * Looks like a transaction already posted. Post again with confirmation to keep both.
 */
class LikelyDuplicate extends LedgerException
{
    public function __construct(public readonly Collection $matches)
    {
        parent::__construct(__('savings::savings.likely_duplicate', ['count' => $matches->count()]));
    }
}
