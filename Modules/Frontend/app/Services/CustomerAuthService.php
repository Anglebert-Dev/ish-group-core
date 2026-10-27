<?php

namespace Modules\Frontend\Services;

use App\Helpers\AuthHelper;
use App\Services\BaseService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Modules\Frontend\Enums\CustomerStatus;
use Modules\Frontend\Models\Customer;
use Modules\Frontend\Repositories\CustomerRepository;

/**
 * Public-site customer accounts — "customer" guard, Customer model.
 */
class CustomerAuthService extends BaseService
{
    public function __construct(
        protected CustomerRepository $customers,
        protected CurrentBusiness $business,
    ) {
    }

    public function register(array $data): array
    {
        $customer = new Customer([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);
        $customer->business_id = $this->business->id();
        $customer->status = CustomerStatus::Active;

        $this->customers->save($customer);

        Auth::guard('customer')->login($customer);
        AuthHelper::claimExclusivePortal('customer');
        $this->recordLoginSuccess($customer);

        return $this->success($customer, __('body.registration_successful'));
    }

    public function login(array $credentials, bool $remember = false): array
    {
        $login = trim((string) ($credentials['login'] ?? $credentials['email'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');

        $customer = $this->findByLogin($login);

        if (! $customer || ! $customer->hasPortalPassword() || ! Hash::check($password, $customer->password)) {
            $this->recordLoginFailure($login);

            return $this->failure(__('body.invalid_credentials'));
        }

        if ($customer->isBanned()) {
            $this->recordLoginFailure($login, 'banned');

            return $this->failure(__('body.account_suspended'));
        }

        Auth::guard('customer')->login($customer, $remember);
        AuthHelper::claimExclusivePortal('customer');
        $this->recordLoginSuccess($customer);

        return $this->success($customer, __('body.login_successful'));
    }

    protected function findByLogin(string $login): ?Customer
    {
        if ($login === '') {
            return null;
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return $this->customers->findByEmail($login);
        }

        $normalized = preg_replace('/\D+/', '', $login) ?: $login;

        return $this->customers->query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->first(function (Customer $customer) use ($login, $normalized) {
                $phone = (string) $customer->phone;
                if ($phone === $login) {
                    return true;
                }

                $phoneDigits = preg_replace('/\D+/', '', $phone) ?: $phone;

                return $phoneDigits === $normalized;
            });
    }

    public function logout(): void
    {
        Auth::guard('customer')->logout();
    }

    public function updateProfile(Customer $customer, array $data): array
    {
        $customer->fill($data);

        $this->customers->save($customer);

        return $this->success($customer, __('body.profile_updated'));
    }

    public function updatePassword(Customer $customer, array $data): array
    {
        if (! Hash::check($data['current_password'], $customer->password)) {
            return $this->failure(__('body.incorrect_current_password'));
        }

        $customer->forceFill(['password' => $data['password']]);
        $this->customers->save($customer);

        if (Auth::guard('customer')->check() && Auth::guard('customer')->id() === $customer->id) {
            Auth::guard('customer')->logoutOtherDevices($data['password']);
        }

        $customer->setRememberToken(Str::random(60));
        $customer->save();

        return $this->success($customer, __('body.password_updated'));
    }

    public function finalizePasswordReset(Customer $customer, string $password): void
    {
        $customer->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();
    }

    public function sendPasswordResetLink(string $email): void
    {
        $customer = $this->customers->findByEmail($email);

        Password::broker('customers')->sendResetLink([
            'email' => $customer?->email ?? strtolower(trim($email)),
            'business_id' => $this->business->id(),
        ]);
    }

    /**
     * @param  array{email: string, password: string, token: string, password_confirmation?: string}  $data
     * @return array{status: bool, message: string}
     */
    public function completePasswordReset(array $data): array
    {
        $customer = $this->customers->findByEmail($data['email'] ?? '');

        $status = Password::broker('customers')->reset(
            [
                'email' => $customer?->email ?? strtolower(trim((string) ($data['email'] ?? ''))),
                'business_id' => $this->business->id(),
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'] ?? $data['password'],
                'token' => $data['token'],
            ],
            function (Customer $customer, string $password) {
                $this->finalizePasswordReset($customer, $password);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, __('body.password_reset_success'));
        }

        return $this->failure(__('body.password_reset_failed'));
    }

    private function recordLoginSuccess(Customer $customer): void
    {
        $customer->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);
        $this->customers->save($customer);

        Log::info('auth.login.success', [
            'guard' => 'customer',
            'user_id' => $customer->id,
            'email' => $customer->email,
            'ip' => request()->ip(),
        ]);
    }

    private function recordLoginFailure(?string $email, ?string $reason = null): void
    {
        Log::warning('auth.login.failure', [
            'guard' => 'customer',
            'email' => $email,
            'reason' => $reason,
            'ip' => request()->ip(),
        ]);
    }
}
