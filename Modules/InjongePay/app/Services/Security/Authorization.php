<?php

namespace Modules\InjongePay\Services\Security;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Models\BusinessProfile;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Support\Money;

/**
 * What a money movement needs, decided by risk, like MoMo and Payoneer: the
 * PIN always; a step-up (authenticator or emailed code) only when something
 * is unusual; a second person above the business's threshold. Limits set by
 * the platform are never exceeded.
 */
class Authorization
{
    public const VELOCITY_PER_HOUR = 5;

    public function __construct(protected Pins $pins, protected Devices $devices)
    {
    }

    /**
     * @return array{step_up: list<string>, second_approval: bool}
     *
     * @throws InjongePayException when a limit would be exceeded
     */
    public function assess(Request $request, User $user, Wallet $wallet, string $operation, int $amount, ?SettlementAccount $destination = null): array
    {
        $profile = BusinessProfile::query()->withoutGlobalScope('business')->where('business_id', $wallet->business_id)->first();
        $this->checkLimits($profile, $wallet, $amount);

        $reasons = [];

        if (! $this->devices->isTrusted($request, $user)) {
            $reasons[] = 'new_device';
        }

        if ($profile?->step_up_above !== null && $amount > $profile->step_up_above) {
            $reasons[] = 'large_amount';
        }

        if ($destination && ! Approval::query()->withoutGlobalScopes()->where('operation', 'cashout')->where('status', Approval::EXECUTED)
            ->where('payload->settlement_account_id', $destination->id)->exists()) {
            $reasons[] = 'first_payout';
        }

        if (Approval::query()->withoutGlobalScopes()->where('requested_by', $user->id)->where('created_at', '>', now()->subHour())->count() >= self::VELOCITY_PER_HOUR) {
            $reasons[] = 'velocity';
        }

        if ($this->pins->isFresh($user)) {
            $reasons[] = 'new_pin';
        }

        return [
            'step_up' => $reasons,
            'second_approval' => $profile?->second_approval_above !== null && $amount > $profile->second_approval_above,
        ];
    }

    private function checkLimits(?BusinessProfile $profile, Wallet $wallet, int $amount): void
    {
        if (! $profile) {
            return;
        }

        if ($profile->limit_per_transaction !== null && $amount > $profile->limit_per_transaction) {
            throw new InjongePayException(__('injongepay::injongepay.err_limit_tx', ['limit' => Money::format($profile->limit_per_transaction)]));
        }

        $spent = fn ($since) => (int) Approval::query()->withoutGlobalScopes()->where('business_id', $wallet->business_id)
            ->whereIn('status', [Approval::AWAITING, Approval::APPROVED, Approval::EXECUTED])->where('created_at', '>=', $since)->sum('amount');

        if ($profile->limit_daily !== null && $spent(today()) + $amount > $profile->limit_daily) {
            throw new InjongePayException(__('injongepay::injongepay.err_limit_daily', ['limit' => Money::format($profile->limit_daily)]));
        }

        if ($profile->limit_monthly !== null && $spent(now()->startOfMonth()) + $amount > $profile->limit_monthly) {
            throw new InjongePayException(__('injongepay::injongepay.err_limit_monthly', ['limit' => Money::format($profile->limit_monthly)]));
        }
    }
}
