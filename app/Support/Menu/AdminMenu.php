<?php

namespace App\Support\Menu;

use App\Helpers\AuthHelper;
use App\Services\Branding\BrandingService;
use App\Services\Module\ModuleManager;
use App\Services\Setting\SettingService;
use App\Support\Branding\ThemeSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Builds the staff sidebar from config/menu.php plus every active module's
 * DataController::modifyAdminMenu() items, filtered by what the signed-in
 * user may see.
 */
class AdminMenu
{
    public function __construct(protected ModuleManager $modules) {}

    /**
     * @return Collection<string, Collection<int, array<string, mixed>>> group label ('' = top) => items
     */
    public function visibleGroups(): Collection
    {
        // The business's menu choices (Branding → Menu) come after the
        // permission filter: they rename, move, reorder or hide, never reveal.
        $menu = $this->overrides();

        $items = $this->allowedItems()
            ->map(fn (array $item) => $this->override($item, $menu['items'][$item['route']] ?? []))
            ->reject(fn (array $item) => $item['hidden']);

        return $items
            ->groupBy(fn (array $item) => (string) $item['group'])
            ->map(fn (Collection $group) => $group->sortBy('order')->values())
            ->sortBy(fn ($group, string $name) => isset($menu['groups'][$name]['order'])
                ? sprintf('%08.2f-%s', $menu['groups'][$name]['order'], strtolower($name))
                : $this->groupRank($name))
            ->mapWithKeys(fn ($group, string $name) => [($name !== '' ? ($menu['groups'][$name]['label'] ?? $name) : '') => $group]);
    }

    /**
     * Every item this person may open, as defined (no business choices):
     * the menu editor's list, and what a landing page may be.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function allowedItems(): Collection
    {
        return collect(config('menu.items', []))
            ->merge($this->moduleItems())
            ->filter(fn ($item) => is_array($item) && isset($item['label'], $item['route']))
            ->filter(fn (array $item) => Route::has($item['route']) && $this->allowed($item))
            ->map(fn (array $item) => $this->normalise($item))
            ->values();
    }

    /** @return array{items: array<string, array<string, mixed>>, groups: array<string, array<string, mixed>>} */
    private function overrides(): array
    {
        $business = app(SettingService::class)->current();
        $menu = $business->exists ? (app(BrandingService::class)->theme($business)['menu'] ?? []) : [];

        return ['items' => $menu['items'] ?? [], 'groups' => $menu['groups'] ?? []];
    }

    /** @param  array<string, mixed>  $choice */
    private function override(array $item, array $choice): array
    {
        return [
            'label' => $choice['label'] ?? $item['label'],
            'icon' => isset($choice['icon']) ? ThemeSchema::ICONS[$choice['icon']] : $item['icon'],
            'group' => $choice['group'] ?? $item['group'],
            'order' => $choice['order'] ?? $item['order'],
            'hidden' => ! empty($choice['hidden']),
        ] + $item;
    }

    /**
     * Every module gets its own sidebar section: items without a 'group'
     * are filed under the module's name (module.json "menu_group" overrides).
     *
     * @return list<array<string, mixed>>
     */
    private function moduleItems(): array
    {
        $items = [];

        foreach ($this->modules->cachedHook('modifyAdminMenu') as $module => $result) {
            if (! is_array($result)) {
                continue;
            }

            $section = $this->modules->menuGroup($module);

            foreach ($result as $item) {
                if (is_array($item)) {
                    $items[] = $item + ['group' => $section];
                }
            }
        }

        return $items;
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
     * alphabetically just before "Management".
     */
    private function groupRank(string $name): string
    {
        $core = array_map(fn ($group) => (string) $group, config('menu.groups', []));
        $position = array_search($name, $core, true);

        if ($position === false) {
            $management = array_search('Management', $core, true);
            $position = ($management === false ? count($core) : $management) - 0.5;
        }

        return sprintf('%08.2f-%s', $position, strtolower($name));
    }
}
