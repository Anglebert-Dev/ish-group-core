<?php

namespace Modules\Production\Services;

use RuntimeException;

/**
 * A count that would break a stage rule. Nothing was saved.
 */
class ProductionRuleBroken extends RuntimeException
{
}
