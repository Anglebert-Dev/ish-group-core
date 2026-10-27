<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfirmStaffPassword;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginStaffRequest;
use App\Http\Requests\Auth\RegisterStaffRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyStaffMfaRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Module\ModuleManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Dashboard (staff) authentication — "staff" guard, User model.
 */
class AuthController extends Controller
{
    public function __construct(
        protected AuthService $auth,
        protected ModuleManager $modules,
    ) {
    }

    public function showRegisterForm(): View|RedirectResponse
    {
        if (! $this->auth->registrationIsOpen()) {
            return redirect()->route('staff.login')->withErrors([
                'email' => __('body.registration_closed'),
            ]);
        }

        return view('auth.register', [
            'isFirstRegistration' => $this->auth->isFirstRegistration(),
            // Extra blocks modules add to the form (e.g. the chosen package).
            'formParts' => $this->auth->isFirstRegistration() ? [] : $this->modules->hook('staff_register_form', request()),
        ]);
    }

    public function register(RegisterStaffRequest $request): RedirectResponse
    {
        $result = $this->auth->registerBusiness($request->validated());

        if (! $result['status']) {
            return redirect()->route('staff.login')->withErrors(['email' => $result['message']]);
        }

        if (! empty($result['data']['pending'])) {
            return redirect()->route('staff.login')->with('success', $result['message']);
        }

        $request->session()->regenerate();

        // A module may send the new business somewhere first (e.g. its invoice).
        $user = $result['data'];
        foreach ($this->modules->hook('registration_redirect', $user->business) as $url) {
            if (is_string($url) && $url !== '') {
                return redirect()->to($url)->with('success', $result['message']);
            }
        }

        return redirect()->route('dashboard')->with('success', $result['message']);
    }

    public function showLoginForm(): View
    {
        return view('auth.login', [
            'registrationIsOpen' => $this->auth->registrationIsOpen(),
            'isFirstRegistration' => $this->auth->isFirstRegistration(),
        ]);
    }

    public function login(LoginStaffRequest $request): RedirectResponse
    {
        $result = $this->auth->login($request->credentials(), $request->boolean('remember'));

        if (! $result['status']) {
            return back()->withErrors(['email' => $result['message']])->onlyInput('email', 'remember');
        }

        if (! empty($result['data']['mfa_required'])) {
            return redirect()->route('staff.mfa.challenge');
        }

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', $result['message']);
    }

    public function showMfaChallenge(): View|RedirectResponse
    {
        if (! session('staff.mfa.pending_user_id')) {
            return redirect()->route('staff.login');
        }

        return view('auth.staff-mfa-challenge');
    }

    public function verifyMfa(VerifyStaffMfaRequest $request): RedirectResponse
    {
        $result = $this->auth->completeMfaLogin($request->validated('code'));

        if (! $result['status']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', $result['message']);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auth->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login')->with('success', __('body.logged_out'));
    }

    public function showForgotPasswordForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        Password::broker('users')->sendResetLink($request->only('email'));

        return back()->with('success', __('body.password_reset_link_sent'));
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $this->auth->finalizePasswordReset($user, $password);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('staff.login')->with('success', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function showConfirmPasswordForm(): View
    {
        return view('auth.confirm-password');
    }

    public function confirmPassword(Request $request): View|RedirectResponse|JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user('staff');

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('body.incorrect_current_password')], 422);
            }

            return back()->withErrors(['password' => __('body.incorrect_current_password')])
                ->with(ConfirmStaffPassword::MODAL_FLAG, true);
        }

        $request->session()->put('auth.staff_password_confirmed_at', time());

        $pending = $request->session()->pull(ConfirmStaffPassword::PENDING_KEY);

        if (is_array($pending) && time() - (int) ($pending['at'] ?? 0) < ConfirmStaffPassword::PENDING_TTL) {
            // The resumed action's back() should land where the user started.
            $request->session()->put('_previous.url', $pending['back']);

            $action = [
                'method' => $pending['method'],
                'url' => $pending['url'],
                'fields' => $this->flattenInput(Crypt::decrypt($pending['input'])),
            ];

            // Pop-up: the page re-submits the action itself, no extra page.
            if ($request->expectsJson()) {
                return response()->json(['action' => $action]);
            }

            return view('auth.confirm-password-resume', $action);
        }

        $intended = $request->session()->pull('url.intended');

        if ($request->expectsJson()) {
            if (! $intended) {
                $request->session()->flash('success', __('body.confirm_password_done'));
            }

            return response()->json(['redirect' => $intended]);
        }

        return $intended ? redirect()->to($intended) : redirect()->route('dashboard');
    }

    /**
     * ['a' => ['x' => 1], 'b' => 2] → [['a[x]', '1'], ['b', '2']]
     *
     * @return list<array{0: string, 1: string}>
     */
    private function flattenInput(array $input, string $prefix = ''): array
    {
        $fields = [];

        foreach ($input as $key => $value) {
            $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                array_push($fields, ...$this->flattenInput($value, $name));
            } else {
                $fields[] = [$name, is_bool($value) ? ($value ? '1' : '0') : (string) $value];
            }
        }

        return $fields;
    }
}
