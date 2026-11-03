<?php

namespace App\Services\Dashboard;

use App\Enums\UserStatus;
use App\Helpers\AuthHelper;
use App\Models\ActivityLog\ActivityLog;
use App\Models\User;
use App\Repositories\Role\RoleRepository;
use App\Repositories\User\UserRepository;
use App\Services\Branding\BrandingService;
use App\Services\Module\ModuleManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * The dashboard is one section per module the business uses: its figures,
 * its buttons and its own content, under the module's name. A small
 * "Team & settings" section and the activity log (off unless the business
 * turns it on) come from the core.
 *
 * Module hooks:
 *   dashboard_stats()          list of ['label', 'value', 'href'?, 'hint'?, 'permission'?]
 *   dashboard_quick_actions()  list of ['label', 'route', 'permission'?, 'color'?, 'description'?, 'icon'?]
 *   dashboard_widget()         a view name or rendered HTML
 */
class DashboardService
{
    public function __construct(
        protected UserRepository $users,
        protected RoleRepository $roles,
        protected ModuleManager $modules,
    ) {}

    /** Off unless a business turns it on in Branding → Dashboard. */
    public const HIDDEN_BY_DEFAULT = ['recent_activity'];

    public function payload(User $user): array
    {
        $sections = $this->sections();
        $settings = $user->business ? (app(BrandingService::class)->theme($user->business)['dashboard'] ?? []) : [];

        return [
            'sections' => $sections,
            'stats' => $this->stats($user),
            'quickActions' => $this->quickActions(),
            'recentActivity' => $this->recentActivity(),
            'blocks' => $this->blocks($user, array_keys($sections), $settings),
            'dashboard' => $settings,
        ];
    }

    /**
     * Every block key this business's dashboard can have (Branding → Dashboard).
     *
     * @param  list<string>  $sectionKeys
     * @return list<string>
     */
    public function blockKeys(array $sectionKeys): array
    {
        return ['welcome', ...$sectionKeys, 'team', 'recent_activity'];
    }

    /**
     * The blocks this person sees, in the business's order. Hiding (for
     * everyone, or for some roles) only takes away: permissions still decide
     * what each block contains.
     *
     * @param  list<string>  $sectionKeys
     * @param  array<string, mixed>  $settings  the theme's "dashboard" section
     * @return list<string>
     */
    public function blocks(User $user, array $sectionKeys, array $settings): array
    {
        $all = $this->blockKeys($sectionKeys);
        $ordered = array_values(array_unique([...array_intersect($settings['order'] ?? [], $all), ...$all]));
        $roles = $user->roles->pluck('id')->all();
        $hidden = $settings['hidden'] ?? self::HIDDEN_BY_DEFAULT;

        return array_values(array_filter($ordered, fn (string $block) => ! in_array($block, $hidden, true)
            && array_intersect($roles, $settings['roles_hidden'][$block] ?? []) === []));
    }

    /**
     * One section per module that has something to show this person.
     *
     * @return array<string, array{title: string, stats: Collection, actions: Collection, widget: string}> keyed "module:<Name>"
     */
    public function sections(): array
    {
        $stats = $this->modules->hook('dashboard_stats');
        $actions = $this->modules->hook('dashboard_quick_actions');
        $widgets = $this->modules->hook('dashboard_widget');
        $sections = [];

        foreach (array_unique([...array_keys($stats), ...array_keys($actions), ...array_keys($widgets)]) as $module) {
            $section = [
                'title' => __($this->modules->menuGroup($module)),
                'stats' => $this->allowedStats(collect(is_array($stats[$module] ?? null) ? $stats[$module] : [])),
                'actions' => $this->allowedActions(collect(is_array($actions[$module] ?? null) ? $actions[$module] : [])),
                'widget' => $this->render($widgets[$module] ?? null),
            ];

            if ($section['stats']->isNotEmpty() || $section['actions']->isNotEmpty() || trim($section['widget']) !== '') {
                $sections['module:'.$module] = $section;
            }
        }

        return $sections;
    }

    /** Team & settings: the core's own figures. */
    public function stats(User $user): Collection
    {
        $core = [];

        if (AuthHelper::can('list users.user')) {
            $core[] = [
                'label' => __('body.active_staff'),
                'value' => $this->users->forBusiness($user->business_id)->where('status', UserStatus::Active->value)->count(),
                'href' => route('users.index'),
            ];
        }

        if (AuthHelper::can('list users.role')) {
            $core[] = [
                'label' => __('body.roles'),
                'value' => $this->roles->forBusiness($user->business_id)->count(),
                'href' => route('roles.index'),
            ];
        }

        return collect($core);
    }

    public function quickActions(): Collection
    {
        $core = [
            [
                'label' => __('body.add_staff'),
                'route' => 'users.create',
                'permission' => 'create users.user',
                'color' => 'teal',
                'description' => __('body.quick_action_add_staff'),
                'icon' => 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
            ],
            [
                'label' => __('body.roles'),
                'route' => 'roles.index',
                'permission' => 'list users.role',
                'color' => 'indigo',
                'description' => __('body.quick_action_roles'),
                'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            ],
            [
                'label' => __('body.settings'),
                'route' => 'settings.index',
                'permission' => 'list settings.setting',
                'color' => 'gray',
                'description' => __('body.quick_action_settings'),
                'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
            ],
        ];

        return $this->allowedActions(collect($core));
    }

    private function allowedStats(Collection $stats): Collection
    {
        return $stats->filter(fn ($stat) => is_array($stat) && isset($stat['label']))
            ->filter(fn (array $stat) => empty($stat['permission']) || AuthHelper::can($stat['permission']))
            ->values();
    }

    private function allowedActions(Collection $actions): Collection
    {
        return $actions->filter(fn ($action) => is_array($action) && isset($action['label'], $action['route']) && Route::has($action['route']))
            ->filter(fn (array $action) => empty($action['permission']) || AuthHelper::can($action['permission']))
            ->map(fn (array $action) => $action + ['color' => 'blue', 'description' => $action['label'], 'icon' => 'M12 6v6m0 0v6m0-6h6m-6 0H6'])
            ->values();
    }

    private function render(mixed $result): string
    {
        return collect(is_array($result) ? $result : [$result])->flatten()->filter()
            ->map(fn ($widget) => is_string($widget) && view()->exists($widget) ? view($widget)->render() : (string) $widget)
            ->implode('');
    }

    public function recentActivity(int $limit = 8): Collection
    {
        if (! AuthHelper::can('list activity.activity_log')) {
            return collect();
        }

        return ActivityLog::query()->with('user')->latest()->limit($limit)->get();
    }
}
