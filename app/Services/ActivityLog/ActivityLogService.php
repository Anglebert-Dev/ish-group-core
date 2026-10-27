<?php

namespace App\Services\ActivityLog;

use App\Enums\ActivityLogAction;
use App\Enums\ActivityLogStatus;
use App\Libraries\GeneralLibrary;
use App\Libraries\ListExport;
use App\Models\ActivityLog\ActivityLog;
use App\Models\Business\Business;
use App\Repositories\ActivityLog\ActivityLogRepository;
use App\Services\BaseService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        'insert_by',
        'update_by',
        'delete_by',
    ];

    private const NOISE_KEYS = [
        'updated_at',
        'update_by',
        'insert_by',
        'delete_by',
        'remember_token',
    ];

    public function __construct(protected ActivityLogRepository $logs)
    {
    }

    public function list(): LengthAwarePaginator
    {
        return $this->logs->paginateRecent();
    }

    public function exportList(): ListExport
    {
        $rows = $this->logs->query()
            ->with('user')
            ->latest()
            ->get()
            ->map(fn (ActivityLog $log) => [
                GeneralLibrary::formatDateTime($log->created_at),
                $log->actorName(),
                $log->action->label(),
                $log->description ?: $log->subjectName(),
            ])
            ->all();

        return ListExport::make(__('body.audit_logs'), 'audit-logs', [
            __('body.when'),
            __('body.actor'),
            __('body.action'),
            __('body.subject'),
        ], $rows);
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
            'action' => $action,
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

    private function businessIdFor(Model $model): ?int
    {
        if ($model instanceof Business) {
            return $model->getKey();
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

        foreach (['name', 'email', 'title', 'reference'] as $attribute) {
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
