<?php

namespace Modules\InjongePay\Services\Security;

use App\Models\User;
use App\Notifications\BrandedNotification;
use App\Services\Auth\StaffMfaService;
use Illuminate\Support\Facades\Hash;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\StepUpCode;

/**
 * The extra check for a risky operation: the authenticator app when the
 * person has one, otherwise a 6-digit code by email (valid 10 minutes).
 */
class StepUp
{
    public function __construct(protected StaffMfaService $mfa)
    {
    }

    public function method(User $user): string
    {
        return $this->mfa->isEnabled($user) ? 'totp' : 'email';
    }

    public function send(User $user, string $purpose): void
    {
        if ($this->method($user) !== 'email') {
            return;
        }

        $code = (string) random_int(100000, 999999);
        StepUpCode::query()->where('user_id', $user->id)->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now()]);
        StepUpCode::query()->create(['user_id' => $user->id, 'purpose' => $purpose, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10)]);

        try {
            $user->notify(new BrandedNotification($user->business, __('injongepay::injongepay.step_up_mail_subject'), [
                __('injongepay::injongepay.step_up_mail_line', ['code' => $code]),
                __('injongepay::injongepay.step_up_mail_warning'),
            ]));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @throws InjongePayException */
    public function check(User $user, string $purpose, ?string $code): void
    {
        $code = preg_replace('/\D/', '', (string) $code);

        if ($code === '') {
            throw new InjongePayException(__('injongepay::injongepay.err_step_up_needed'));
        }

        if ($this->method($user) === 'totp') {
            if (! $this->mfa->verify($user, $code)) {
                throw new InjongePayException(__('injongepay::injongepay.err_step_up_wrong'));
            }

            return;
        }

        $row = StepUpCode::query()->where('user_id', $user->id)->where('purpose', $purpose)->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();

        if (! $row || $row->attempts >= 5) {
            throw new InjongePayException(__('injongepay::injongepay.err_step_up_expired'));
        }

        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('attempts');

            throw new InjongePayException(__('injongepay::injongepay.err_step_up_wrong'));
        }

        $row->forceFill(['used_at' => now()])->save();
    }
}
