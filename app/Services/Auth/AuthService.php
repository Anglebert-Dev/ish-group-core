<?php

namespace App\Services\Auth;

use App\Enums\ActivityLogAction;
use App\Helpers\AuthHelper;
use App\Models\System\System;
use App\Models\User;
use App\Repositories\User\UserRepository;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\BaseService;
use App\Services\Business\BusinessSetupService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Staff (dashboard) authentication — uses the "staff" guard / User model.
 */
class AuthService extends BaseService
{
    public function __construct(
        protected UserRepository $users,
        protected BusinessSetupService $businesses,
        protected StaffMfaService $mfa,
    ) {
    }

    /**
     * The very first sign-up bootstraps the platform (its owner becomes the
     * superadmin). After that, sign-ups create new businesses only while the
     * superadmin keeps "allow_registration" on.
     */
    public function registrationIsOpen(): bool
    {
        return $this->isFirstRegistration()
            || (bool) System::getProperty('allow_registration', false);
    }

    public function isFirstRegistration(): bool
    {
        return ! $this->users->existsAny();
    }

    public function registerBusiness(array $data): array
    {
        if (! $this->registrationIsOpen()) {
            return $this->failure(__('body.registration_closed'));
        }

        $first = $this->isFirstRegistration();

        $result = $this->businesses->createWithOwner(
            [
                'name' => $data['business_name'],
                'is_active' => $first || (bool) System::getProperty('default_business_active_status', true),
            ],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_superadmin' => $first,
            ],
            ['package' => $data['package'] ?? null],
        );

        if (! $result['status']) {
            return $result;
        }

        $user = $result['data']['owner'];

        if ($first) {
            System::addProperty('primary_business_id', $result['data']['business']->id);
        }

        if (! $result['data']['business']->is_active) {
            return $this->success(['pending' => true], __('body.business_awaiting_activation'));
        }

        Auth::guard('staff')->login($user);
        AuthHelper::claimExclusivePortal('staff');
        $this->recordLoginSuccess($user);

        return $this->success($user, __('body.registration_successful'));
    }

    public function login(array $credentials, bool $remember = false): array
    {
        // Check credentials without signing in, so a sign-in that still needs
        // an MFA code (or is refused) does not count as a login.
        $guard = Auth::guard('staff');
        $user = $guard->getProvider()->retrieveByCredentials(['email' => $credentials['email'] ?? '']);

        if (! $user || ! $guard->getProvider()->validateCredentials($user, $credentials)) {
            $this->recordLoginFailure('staff', $credentials['email'] ?? null, null, $user);

            return $this->failure(__('body.invalid_credentials'));
        }

        if ($user->isBanned()) {
            $this->recordLoginFailure('staff', $credentials['email'] ?? null, 'banned', $user);

            return $this->failure(__('body.account_suspended'));
        }

        if (! $user->isApproved()) {
            $this->recordLoginFailure('staff', $credentials['email'] ?? null, 'pending', $user);

            return $this->failure(__('body.account_awaiting_approval'));
        }

        if (! $user->isSuperAdmin() && ! $user->business?->is_active) {
            $this->recordLoginFailure('staff', $credentials['email'] ?? null, 'business_inactive', $user);

            return $this->failure(__('body.business_inactive'));
        }

        if ($this->mfa->isEnabled($user)) {
            session([
                'staff.mfa.pending_user_id' => $user->id,
                'staff.mfa.remember' => $remember,
            ]);

            return $this->success(['mfa_required' => true], __('body.mfa_required'));
        }

        $guard->login($user, $remember);
        AuthHelper::claimExclusivePortal('staff');
        $this->recordLoginSuccess($user);

        return $this->success($user, __('body.login_successful'));
    }

    public function completeMfaLogin(string $code): array
    {
        $userId = session('staff.mfa.pending_user_id');
        $remember = (bool) session('staff.mfa.remember', false);

        if (! $userId) {
            return $this->failure(__('body.mfa_invalid'));
        }

        $user = User::query()->find($userId);

        // A live TOTP code is checked first; only falls back to a recovery
        // code (consuming it) if that fails, so a valid TOTP code never
        // burns a recovery code by mistake.
        $verified = $user && ($this->mfa->verify($user, $code) || $this->mfa->verifyRecoveryCode($user, $code));

        if (! $verified) {
            $this->recordLoginFailure('staff', $user?->email, 'mfa', $user);

            return $this->failure(__('body.mfa_invalid'));
        }

        Auth::guard('staff')->login($user, $remember);
        session()->forget(['staff.mfa.pending_user_id', 'staff.mfa.remember']);
        AuthHelper::claimExclusivePortal('staff');
        $this->recordLoginSuccess($user);

        return $this->success($user, __('body.login_successful'));
    }

    public function logout(): void
    {
        Auth::guard('staff')->logout();
    }

    public function updateProfile(User $user, array $data): array
    {
        $user->fill($data);

        $this->users->save($user);

        return $this->success($user, __('body.profile_updated'));
    }

    public function updatePassword(User $user, array $data): array
    {
        if (! Hash::check($data['current_password'], $user->password)) {
            return $this->failure(__('body.incorrect_current_password'));
        }

        $user->forceFill(['password' => $data['password']]);
        $this->users->save($user);
        $this->invalidateOtherSessions('staff', $user, $data['password']);
        app(ActivityLogService::class)->log(ActivityLogAction::PasswordChanged, __('body.audit_log_password_changed', ['name' => $user->name]), $user);

        return $this->success($user, __('body.password_updated'));
    }

    public function finalizePasswordReset(User $user, string $password): void
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        $this->purgeUserSessions($user->getAuthIdentifier());
    }

    private function invalidateOtherSessions(string $guard, User $user, string $password): void
    {
        if (Auth::guard($guard)->check() && Auth::guard($guard)->id() === $user->getAuthIdentifier()) {
            Auth::guard($guard)->logoutOtherDevices($password);
        }

        $user->setRememberToken(Str::random(60));
        $user->save();
        $this->purgeUserSessions($user->getAuthIdentifier(), session()->getId());
    }

    private function purgeUserSessions(int|string $userId, ?string $exceptSessionId = null): void
    {
        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId);

        if ($exceptSessionId) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->delete();
    }

    private function recordLoginSuccess(User $user): void
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);
        $this->users->save($user);

        Log::info('auth.login.success', [
            'guard' => 'staff',
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => request()->ip(),
        ]);
    }

    private function recordLoginFailure(string $guard, ?string $email, ?string $reason = null, ?User $user = null): void
    {
        Log::warning('auth.login.failure', [
            'guard' => $guard,
            'email' => $email,
            'reason' => $reason,
            'ip' => request()->ip(),
        ]);

        app(ActivityLogService::class)->log(
            ActivityLogAction::LoginFailed,
            __('body.audit_log_failed', ['login' => (string) $email, 'portal' => __('body.audit_portal_staff')]).($reason ? ' ('.$reason.')' : ''),
            $user,
            ['guard' => $guard, 'login' => $email, 'reason' => $reason],
            $user?->business_id,
        );
    }
}
