<?php

namespace Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Frontend\Http\Requests\LoginCustomerRequest;
use Modules\Frontend\Http\Requests\RegisterCustomerRequest;
use Modules\Frontend\Services\CustomerAuthService;

/**
 * Public-site customer authentication — "customer" guard, Customer model.
 */
class CustomerAuthController extends Controller
{
    public function __construct(protected CustomerAuthService $auth)
    {
    }

    public function register(RegisterCustomerRequest $request): RedirectResponse
    {
        $result = $this->auth->register($request->validated());

        return $this->redirectAfterAuth($request, $result['message']);
    }

    public function showLoginForm(): View
    {
        $this->rememberReturnUrl(request());

        return view('frontend::auth.login');
    }

    public function showRegisterForm(): View
    {
        $this->rememberReturnUrl(request());

        return view('frontend::auth.register');
    }

    public function login(LoginCustomerRequest $request): RedirectResponse
    {
        $result = $this->auth->login($request->credentials(), $request->boolean('remember'));

        if (! $result['status']) {
            return back()->withErrors(['login' => $result['message']])->onlyInput('login', 'remember');
        }

        return $this->redirectAfterAuth($request, $result['message']);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auth->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('site.home')->with('success', __('body.logged_out'));
    }

    /**
     * Send the customer back where they were heading after login/signup.
     * Laravel stores url.intended when auth middleware blocks a page; we
     * re-apply it after session regenerate and also accept an explicit
     * same-site ?redirect= from login/register links.
     */
    private function redirectAfterAuth(Request $request, string $message): RedirectResponse
    {
        $intended = $request->session()->get('url.intended');

        $request->session()->regenerate();

        if (is_string($intended) && $intended !== '') {
            $request->session()->put('url.intended', $intended);
        }

        return redirect()->intended(route('profile'))->with('success', $message);
    }

    private function rememberReturnUrl(Request $request): void
    {
        $redirect = $request->query('redirect');

        if (! is_string($redirect) || $redirect === '') {
            return;
        }

        if (! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return;
        }

        $request->session()->put('url.intended', url($redirect));
    }

    public function showForgotPasswordForm(): View
    {
        return view('frontend::auth.forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->auth->sendPasswordResetLink($request->validated('email'));

        return back()->with('success', __('body.password_reset_link_sent'));
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('frontend::auth.reset-password', [
            'token' => $token,
            'email' => strtolower((string) $request->query('email', '')),
            'welcome' => $request->boolean('welcome'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $result = $this->auth->completePasswordReset($request->validated() + [
            'password_confirmation' => $request->input('password_confirmation'),
        ]);

        return $result['status']
            ? redirect()->route('login')->with('success', $result['message'])
            : back()->withErrors(['email' => $result['message']]);
    }
}
