<?php

namespace Modules\InjongePay\Exceptions;

/** The operation is fine but needs the extra code (authenticator or email) first. */
class StepUpRequired extends InjongePayException
{
    /** @param list<string> $reasons */
    public function __construct(public readonly array $reasons, public readonly string $method)
    {
        parent::__construct(__('injongepay::injongepay.step_up_needed_'.$method));
    }
}
