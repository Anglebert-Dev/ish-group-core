<?php

namespace App\Support\Printing;

use App\Services\Module\ModuleManager;

/**
 * Every printable document type: the core's own plus each active module's
 * DataController::printables() (type => Printable class).
 */
class PrintRegistry
{
    /** @var array<string, class-string<Printable>>|null */
    private ?array $types = null;

    /**
     * @return array<string, class-string<Printable>>
     */
    public function types(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $types = (array) config('printing.types', []);

        foreach (app(ModuleManager::class)->hook('printables') as $printables) {
            foreach ((array) $printables as $type => $class) {
                if (is_string($type) && is_string($class) && is_subclass_of($class, Printable::class)) {
                    $types[$type] = $class;
                }
            }
        }

        return $this->types = $types;
    }

    public function get(string $type): ?Printable
    {
        $class = $this->types()[$type] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * Labels for the design page, sorted.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        return collect($this->types())->map(fn (string $class) => app($class)->label())->sort()->all();
    }
}
