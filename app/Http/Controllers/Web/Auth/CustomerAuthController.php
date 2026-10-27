<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginCustomerRequest;
use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\CustomerAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public booking-portal authentication — "customer" guard, Customer model.
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
        $this->rememberBookingReturnUrl(request());

        return view('auth.customer-login');
    }

    public function showRegisterForm(): View
    {
        $this->rememberBookingReturnUrl(request());

        return view('auth.customer-register');
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

        return redirect()->route('home')->with('success', __('body.logged_out'));
    }

    /**
     * Keep guests in the booking wizard after auth (login or signup).
     * Laravel already stores url.intended when auth middleware blocks
     * /booking/details; we re-apply it after session regenerate and also
     * accept an explicit ?redirect= from login/register links.
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

    private function rememberBookingReturnUrl(Request $request): void
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
        return view('auth.customer-forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->auth->sendPasswordResetLink($request->validated('email'));

        return back()->with('success', __('body.password_reset_link_sent'));
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.customer-reset-password', [
            'token' => $token,
            'email' => strtolower((string) $request->query('email', '')),
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
