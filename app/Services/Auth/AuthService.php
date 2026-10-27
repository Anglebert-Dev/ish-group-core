<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Helpers\AuthHelper;
use App\Models\User;
use App\Repositories\User\UserRepository;
use App\Services\BaseService;
use App\Services\Permission\PermissionService;
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
        protected PermissionService $permissions,
        protected StaffMfaService $mfa,
    ) {
    }

    public function registrationIsOpen(): bool
    {
        return ! $this->users->existsAny();
    }

    public function registerFirstSuperAdmin(array $data): array
    {
        if (! $this->registrationIsOpen()) {
            return $this->failure(__('body.registration_closed'));
        }

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->status = UserStatus::Active;

        $this->users->save($user);

        $this->permissions->initPermissions();
        $user->assignRole('SuperAdmin');

        Auth::guard('staff')->login($user);
        AuthHelper::claimExclusivePortal('staff');
        $this->recordLoginSuccess($user);

        return $this->success($user, __('body.registration_successful'));
    }

    public function login(array $credentials, bool $remember = false): array
    {
        if (! Auth::guard('staff')->attempt($credentials, $remember)) {
            $this->recordLoginFailure('staff', $credentials['email'] ?? null);
            return $this->failure(__('body.invalid_credentials'));
        }

        $user = Auth::guard('staff')->user();

        if ($user->isBanned()) {
            Auth::guard('staff')->logout();
            $this->recordLoginFailure('staff', $credentials['email'] ?? null, 'banned');

            return $this->failure(__('body.account_suspended'));
        }

        if (! $user->isApproved()) {
            Auth::guard('staff')->logout();
            $this->recordLoginFailure('staff', $credentials['email'] ?? null, 'pending');

            return $this->failure(__('body.account_awaiting_approval'));
        }

        if ($this->mfa->isEnabled($user)) {
            Auth::guard('staff')->logout();
            session([
                'staff.mfa.pending_user_id' => $user->id,
                'staff.mfa.remember' => $remember,
            ]);

            return $this->success(['mfa_required' => true], __('body.mfa_required'));
        }

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
            $this->recordLoginFailure('staff', $user?->email, 'mfa');

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

    private function recordLoginFailure(string $guard, ?string $email, ?string $reason = null): void
    {
        Log::warning('auth.login.failure', [
            'guard' => $guard,
            'email' => $email,
            'reason' => $reason,
            'ip' => request()->ip(),
        ]);
    }
}
