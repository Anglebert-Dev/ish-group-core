<?php

namespace Modules\Billing\Enums;

/**
 * What paying the invoice does to the subscription.
 */
enum InvoiceReason: string
{
    case NewSubscription = 'new';   // start the subscription
    case Renewal = 'renewal';       // extend it by one period
    case Change = 'change';         // switch to another package now (upgrade)
}
