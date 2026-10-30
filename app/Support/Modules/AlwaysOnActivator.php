<?php

namespace App\Support\Modules;

use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;

/**
 * Module code is always loaded. Whether a module is ON is stored in the
 * database (system table, "<alias>_enabled"), read by ModuleManager, so
 * switching a module on a server never touches files in the repository.
 */
class AlwaysOnActivator implements ActivatorInterface
{
    public function enable(Module $module): void
    {
    }

    public function disable(Module $module): void
    {
    }

    public function hasStatus(Module|string $module, bool $status): bool
    {
        return $status;
    }

    public function setActive(Module $module, bool $active): void
    {
    }

    public function setActiveByName(string $name, bool $active): void
    {
    }

    public function delete(Module $module): void
    {
    }

    public function reset(): void
    {
    }
}
