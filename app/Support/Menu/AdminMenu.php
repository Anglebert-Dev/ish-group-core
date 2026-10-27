<?php

namespace App\Support\Menu;

use App\Helpers\AuthHelper;
use App\Services\Module\ModuleManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Builds the staff sidebar from config/menu.php plus every active module's
 * DataController::modifyAdminMenu() items, filtered by what the signed-in
 * user may see.
 */
class AdminMenu
{
    public function __construct(protected ModuleManager $modules)
    {
    }

    /**
     * @return Collection<string, Collection<int, array<string, mixed>>> group label ('' = top) => items
     */
    public function visibleGroups(): Collection
    {
        $items = collect(config('menu.items', []))
            ->merge($this->modules->collectHook('modifyAdminMenu'))
            ->filter(fn ($item) => is_array($item) && isset($item['label'], $item['route']))
            ->filter(fn (array $item) => Route::has($item['route']) && $this->allowed($item))
            ->map(fn (array $item) => $this->normalise($item));

        return $items
            ->groupBy(fn (array $item) => (string) $item['group'])
            ->map(fn (Collection $group) => $group->sortBy('order')->values())
            ->sortBy(fn ($group, string $name) => $this->groupRank($name));
    }

    private function allowed(array $item): bool
    {
        if (! empty($item['ability'])) {
            return (bool) auth('staff')->user()?->can($item['ability']);
        }

        return empty($item['permission']) || AuthHelper::can($item['permission']);
    }

    private function normalise(array $item): array
    {
        $label = (string) $item['label'];

        return [
            'label' => str_contains($label, '.') && trans()->has($label) ? __($label) : $label,
            'route' => $item['route'],
            'active' => $item['active'] ?? $item['route'].'*',
            'group' => $item['group'] ?? null,
            'order' => $item['order'] ?? 50,
            'icon' => $item['icon'] ?? 'M4 6h16M4 12h16M4 18h16',
        ];
    }

    /**
     * Core groups keep their configured order; module groups slot in
     * alphabetically after the top section and before "Management".
     */
    private function groupRank(string $name): string
    {
        $core = array_map(fn ($group) => (string) $group, config('menu.groups', []));
        $index = array_search($name, $core, true);

        if ($index === 0) {
            return '0';
        }

        if ($index !== false) {
            return '2-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
        }

        return '1-'.strtolower($name);
    }
}
