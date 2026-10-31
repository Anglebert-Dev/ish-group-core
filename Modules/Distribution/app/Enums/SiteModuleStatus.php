<?php

namespace Modules\Distribution\Enums;

enum SiteModuleStatus: string
{
    case PendingInstall = 'pending_install';   // assigned, not on the site yet
    case Installed = 'installed';
    case UpdateAvailable = 'update_available';
    case Failed = 'failed';                    // last install/update failed on the site

    public function label(): string
    {
        return __('distribution::distribution.module_status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Installed => 'success',
            self::PendingInstall => 'muted',
            self::UpdateAvailable => 'warning',
            self::Failed => 'danger',
        };
    }
}
