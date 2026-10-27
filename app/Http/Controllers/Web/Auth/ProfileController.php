<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateStaffPasswordRequest;
use App\Http\Requests\Auth\UpdateStaffProfileRequest;
use App\Http\Requests\Auth\VerifyStaffMfaRequest;
use App\Libraries\TotpLibrary;
use App\Services\Auth\AuthService;
use App\Services\Auth\StaffMfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;

/**
 * Staff's own account page: view/update profile, change password, logout.
 */
class ProfileController extends Controller
{
    public function __construct(
        protected AuthService $auth,
        protected StaffMfaService $mfa,
    ) {
    }

    public function show(): View
    {
        $user = Auth::guard('staff')->user();
        $mfaSetup = session('staff.mfa.setup');

        
        $justCreated = (bool) session('staff.mfa.setup_just_created', false);
        $errors = session('errors');
        $justFailedMfaConfirm = $errors instanceof MessageBag && $errors->has('code');

        if ($mfaSetup && ! $justCreated && ! $justFailedMfaConfirm) {
            session()->forget('staff.mfa.setup');
            $mfaSetup = null;
        }

        $mfaEnabled = $this->mfa->isEnabled($user);

        return view('app.profile.index', [
            'user' => $user,
            'mfaEnabled' => $mfaEnabled,
            'mfaSetup' => $mfaSetup,
            'mfaQrCode' => $mfaSetup ? TotpLibrary::qrCodeDataUri($mfaSetup['otpauth_url']) : null,
            'mfaRecoveryCodes' => session('staff.mfa.recovery_codes'),
            'mfaRecoveryRemaining' => $mfaEnabled ? $this->mfa->recoveryCodesRemaining($user) : null,
        ]);
    }

    public function update(UpdateStaffProfileRequest $request): RedirectResponse
    {
        $result = $this->auth->updateProfile(Auth::guard('staff')->user(), $request->validated());

        return back()->with('success', $result['message']);
    }

    public function updatePassword(UpdateStaffPasswordRequest $request): RedirectResponse
    {
        $result = $this->auth->updatePassword(Auth::guard('staff')->user(), $request->validated());

        if (! $result['status']) {
            return back()->withErrors(['current_password' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function beginMfaSetup(): RedirectResponse
    {
        $result = $this->mfa->beginSetup(Auth::guard('staff')->user());
        session(['staff.mfa.setup' => $result['data']]);
        session()->flash('staff.mfa.setup_just_created', true);

        return back()->with('success', $result['message']);
    }

    public function cancelMfaSetup(): RedirectResponse
    {
        session()->forget('staff.mfa.setup');

        return back()->with('success', __('body.mfa_setup_cancelled'));
    }

    public function confirmMfaSetup(VerifyStaffMfaRequest $request): RedirectResponse
    {
        $result = $this->mfa->confirmSetup(Auth::guard('staff')->user(), $request->validated('code'));

        if (! $result['status']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        session()->forget('staff.mfa.setup');
        session()->flash('staff.mfa.recovery_codes', $result['data']['recovery_codes']);

        return back()->with('success', $result['message']);
    }

    public function disableMfa(): RedirectResponse
    {
        $result = $this->mfa->disable(Auth::guard('staff')->user());
        session()->forget(['staff.mfa.setup', 'staff.mfa.recovery_codes']);

        return back()->with('success', $result['message']);
    }

    public function regenerateRecoveryCodes(): RedirectResponse
    {
        $codes = $this->mfa->generateRecoveryCodes(Auth::guard('staff')->user());
        session()->flash('staff.mfa.recovery_codes', $codes);

        return back()->with('success', __('body.mfa_recovery_regenerated'));
    }
}
