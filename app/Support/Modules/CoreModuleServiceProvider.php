<?php

namespace App\Support\Modules;

use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Base provider for every module in this core. Same as nwidart's, except a
 * module's migrations are NOT loaded into the global `php artisan migrate`:
 * they run when the module is installed (ModuleManager::install, the
 * super-admin Modules page or `php artisan core:module install <Name>`).
 */
abstract class CoreModuleServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
    }
}
