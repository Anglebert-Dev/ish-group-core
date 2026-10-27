<?php

namespace App\Services\ActivityLog;

use App\Enums\ActivityLogAction;
use App\Enums\ActivityLogStatus;
use App\Libraries\GeneralLibrary;
use App\Libraries\ListExport;
use App\Models\ActivityLog\ActivityLog;
use App\Models\Business\Business;
use App\Models\System\System;
use App\Models\User;
use App\Repositories\ActivityLog\ActivityLogRepository;
use App\Services\BaseService;
use App\Support\Tenancy\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

class ActivityLogService extends BaseService
{
    private const HIDDEN_KEYS = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
        'content',
        'insert_by',
        'update_by',
        'delete_by',
    ];

    private const NOISE_KEYS = [
        'updated_at',
        'last_login_at',
        'last_login_ip',
        'update_by',
        'insert_by',
        'delete_by',
        'remember_token',
    ];

    public function __construct(protected ActivityLogRepository $logs)
    {
    }

    /**
     * @param  array{q?: ?string, action?: ?string, user?: ?string, business?: ?string, from?: ?string, to?: ?string}  $filters
     * @param  bool  $allBusinesses  superadmin view across every business
     */
    public function list(array $filters = [], bool $allBusinesses = false): LengthAwarePaginator
    {
        return $this->filtered($filters, $allBusinesses)->with(['user', 'business'])->paginate(25)->withQueryString();
    }

    public function exportList(array $filters = [], bool $allBusinesses = false): ListExport
    {
        $rows = $this->filtered($filters, $allBusinesses)
            ->with(['user', 'business'])
            ->limit(5000)
            ->get()
            ->map(fn (ActivityLog $log) => array_values(array_filter([
                GeneralLibrary::formatDateTime($log->created_at),
                $allBusinesses ? ($log->business?->name ?? __('body.audit_platform')) : null,
                $log->actorName(),
                $log->actionLabel(),
                $log->description ?: $log->subjectName(),
                (string) $log->ip_address,
            ], fn ($v) => $v !== null)))
            ->all();

        return ListExport::make(__('body.audit_logs'), 'audit-logs', array_values(array_filter([
            __('body.when'),
            $allBusinesses ? __('body.business') : null,
            __('body.actor'),
            __('body.action'),
            __('body.subject'),
            'IP',
        ])), $rows);
    }

    public function filtered(array $filters, bool $allBusinesses = false): Builder
    {
        $query = $allBusinesses ? ActivityLog::query()->withoutGlobalScope('business') : ActivityLog::query();
        $search = trim((string) ($filters['q'] ?? ''));

        return $query
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['user'] ?? null, fn ($q, $user) => $q->where('user_id', $user))
            ->when($allBusinesses && filled($filters['business'] ?? null), fn ($q) => $filters['business'] === 'platform'
                ? $q->whereNull('business_id')
                : $q->where('business_id', $filters['business']))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('description', 'like', "%{$search}%")
                ->orWhere('subject_label', 'like', "%{$search}%")
                ->orWhere('actor_name', 'like', "%{$search}%")
                ->orWhere('ip_address', 'like', "%{$search}%")))
            ->tap(fn (Builder $q) => $this->sort($q, $filters['sort'] ?? 'when', $filters['dir'] ?? 'desc', $allBusinesses));
    }

    public const SORTS = ['when', 'business', 'actor', 'action'];

    private function sort(Builder $query, string $sort, string $dir, bool $allBusinesses): void
    {
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        match ($allBusinesses || $sort !== 'business' ? $sort : 'when') {
            'business' => $query->orderBy(
                Business::query()->select('name')->whereColumn('businesses.id', 'activity_logs.business_id'),
                $dir,
            ),
            'actor' => $query->orderBy('actor_name', $dir),
            'action' => $query->orderBy('action', $dir),
            default => $query->orderBy('created_at', $dir),
        };

        $query->orderBy('id', $dir);
    }

    public function details(ActivityLog $log): ActivityLog
    {
        $log->load(['user', 'subject']);

        return $log;
    }

    public function record(Model $model, string|ActivityLogAction $action, array $old = []): void
    {
        if ($model instanceof ActivityLog) {
            return;
        }

        $action = $action instanceof ActivityLogAction
            ? $action
            : ActivityLogAction::from($action);

        $attributes = $this->scalarAttributes($model, $model->getAttributes());
        $old = $this->scalarAttributes($model, $old);

        if ($action === ActivityLogAction::Updated) {
            $changes = array_diff_key($model->getChanges(), array_flip(self::NOISE_KEYS));
            if ($changes === []) {
                return;
            }

            $changedKeys = array_keys($changes);
            $attributes = array_intersect_key($attributes, array_flip($changedKeys));
            $old = array_intersect_key($old, array_flip($changedKeys));
        }

        if (self::$muted > 0) {
            return;
        }

        $staff = Auth::guard('staff')->user();
        // The customer guard only exists while the Frontend module is installed.
        $customer = config('auth.guards.customer') ? Auth::guard('customer')->user() : null;
        $subjectLabel = $this->subjectLabel($model);
        $request = request();

        $log = new ActivityLog([
            'status' => ActivityLogStatus::Active,
            'business_id' => $this->businessIdFor($model),
            'user_id' => $staff?->id,
            'actor_name' => $staff?->name ?? $customer?->name,
            'action' => $action->value,
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'subject_label' => $subjectLabel,
            'description' => $action->label().' '.$subjectLabel,
            'properties' => [
                'attributes' => $attributes,
                'old' => $old,
            ],
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 512, ''),
        ]);

        $this->logs->save($log);
    }

    /**
     * Log an event that is not a record change: logins, exports, module
     * installs, approvals, ...
     */
    public function log(
        ActivityLogAction|string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?int $businessId = null,
        ?User $actor = null,
    ): void {
        if (self::$muted > 0) {
            return;
        }

        try {
            $staff = $actor ?? Auth::guard('staff')->user();
            $customer = $actor ? null : (config('auth.guards.customer') ? Auth::guard('customer')->user() : null);
            $request = request();

            $this->logs->save(new ActivityLog([
                'status' => ActivityLogStatus::Active,
                'business_id' => $businessId ?? ($subject ? $this->businessIdFor($subject) : null) ?? $staff?->business_id ?? $customer?->business_id,
                'user_id' => $staff?->id,
                'actor_name' => $staff?->name ?? $customer?->name,
                'action' => $action instanceof ActivityLogAction ? $action->value : $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subject ? $this->subjectLabel($subject) : null,
                'description' => Str::limit($description, 250, ''),
                'properties' => $properties ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => Str::limit((string) $request?->userAgent(), 512, ''),
            ]));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static int $muted = 0;

    /**
     * Translation groups searched for module action labels
     * ("<group>.<action>"), one per module: "<alias>::audit".
     *
     * @return list<string>
     */
    public static function customLabels(): array
    {
        return collect(app(\App\Services\Module\ModuleManager::class)->activeModules())
            ->map(fn (string $name) => strtolower($name).'::audit')
            ->all();
    }

    /**
     * Every action value that occurs, for the filter: core ones plus any
     * module actions already logged.
     *
     * @return array<string, string>
     */
    public function actionOptions(bool $allBusinesses = false): array
    {
        $query = $allBusinesses ? ActivityLog::query()->withoutGlobalScope('business') : ActivityLog::query();
        $custom = $query->distinct()->pluck('action')
            ->reject(fn ($value) => ActivityLogAction::tryFrom((string) $value))
            ->mapWithKeys(fn ($value) => [$value => (new ActivityLog(['action' => $value]))->actionLabel()])
            ->all();

        return ActivityLogAction::options() + $custom;
    }

    /**
     * Run $callback without event logging (e.g. the silent login that
     * impersonation performs — it is logged as impersonation instead).
     */
    public static function muted(callable $callback): mixed
    {
        self::$muted++;

        try {
            return $callback();
        } finally {
            self::$muted--;
        }
    }

    private function businessIdFor(Model $model): ?int
    {
        if ($model instanceof Business) {
            return $model->getKey();
        }

        // Platform-wide records (settings) belong to no business.
        if ($model instanceof System) {
            return null;
        }

        return $model->getAttribute('business_id') ?? app(CurrentBusiness::class)->id();
    }

    private function subjectLabel(Model $model): string
    {
        if (method_exists($model, 'name')) {
            try {
                $name = $model->name();
                if (is_string($name) && $name !== '') {
                    return $name;
                }
            } catch (Throwable) {
            }
        }

        if ($model instanceof \App\Models\Role\Role) {
            return $model->display_name;
        }

        if ($model instanceof System) {
            return (string) $model->key;
        }

        foreach (['name', 'email', 'title', 'reference', 'number', 'code', 'subject'] as $attribute) {
            $value = $model->getAttribute($attribute);
            if (is_scalar($value) && $value !== '') {
                return (string) $value;
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    private function scalarAttributes(Model $model, array $attributes): array
    {
        $hidden = array_unique(array_merge($model->getHidden(), self::HIDDEN_KEYS));
        $out = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $hidden, true) || str_starts_with((string) $key, '_')) {
                continue;
            }

            $out[$key] = $this->scalarValue($value);
        }

        return $out;
    }

    private function scalarValue(mixed $value): mixed
    {
        if ($value instanceof UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value) || is_object($value)) {
            return json_decode(json_encode($value), true);
        }

        return $value;
    }
}
