<?php

namespace App\Services\SuperAdmin;

use App\Models\User;
use App\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class ImpersonationService extends BaseService
{
    public const SESSION_KEY = 'impersonation';

    public function isImpersonating(Request $request): bool
    {
        return $request->session()->has(self::SESSION_KEY);
    }

    /**
     * @return array{impersonator_id: int, impersonator_name: string, return_url: string}|null
     */
    public function current(Request $request): ?array
    {
        return $request->session()->get(self::SESSION_KEY);
    }

    public function start(Request $request, User $impersonator, User $target): array
    {
        if ($this->isImpersonating($request)) {
            return $this->failure(__('body.impersonation_already_active'));
        }

        if (! $impersonator->isSuperAdmin()) {
            return $this->failure(__('body.no_permission'));
        }

        if ($target->is($impersonator) || $target->isSuperAdmin()) {
            return $this->failure(__('body.impersonation_not_allowed'));
        }

        if (! $target->isApproved() || ! $target->business?->is_active) {
            return $this->failure(__('body.impersonation_inactive_target'));
        }

        $returnUrl = route('superadmin.businesses.show', $target->business);

        Auth::guard('staff')->login($target);
        $request->session()->regenerate();
        $request->session()->forget('auth.staff_password_confirmed_at');
        $request->session()->put(self::SESSION_KEY, [
            'impersonator_id' => $impersonator->id,
            'impersonator_name' => $impersonator->name,
            'return_url' => $returnUrl,
        ]);

        Log::warning('auth.impersonation.start', [
            'impersonator_id' => $impersonator->id,
            'target_id' => $target->id,
            'target_business_id' => $target->business_id,
            'ip' => $request->ip(),
        ]);

        return $this->success($target, __('body.impersonation_started', ['name' => $target->name]));
    }

    public function leave(Request $request): array
    {
        $state = $this->current($request);
        $impersonatedId = Auth::guard('staff')->id();

        // Back to the stored superadmin only, and only if they still are one.
        $impersonator = $state ? User::query()->find($state['impersonator_id']) : null;

        if (! $impersonator || ! $impersonator->isSuperAdmin() || ! $impersonator->isApproved()) {
            Auth::guard('staff')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->failure(__('body.impersonation_return_failed'));
        }

        Auth::guard('staff')->login($impersonator);
        $request->session()->regenerate();
        $request->session()->forget([self::SESSION_KEY, 'auth.staff_password_confirmed_at']);

        Log::info('auth.impersonation.end', [
            'impersonator_id' => $impersonator->id,
            'target_id' => $impersonatedId,
            'ip' => $request->ip(),
        ]);

        return $this->success(['return_url' => $state['return_url']], __('body.impersonation_ended'));
    }
}
