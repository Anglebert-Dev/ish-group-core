<?php

namespace Modules\Distribution\Enums;

/** How a site takes the updates we publish (set per site, by us). */
enum UpdateMode: string
{
    case Auto = 'auto';       // installs on its next check
    case Manual = 'manual';   // waits for the site's admin to click Update

    public function label(): string
    {
        return __('distribution::distribution.mode_'.$this->value);
    }
}
