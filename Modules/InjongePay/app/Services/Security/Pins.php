<?php

namespace Modules\InjongePay\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\TransactionPin;

/**
 * The 5-digit transaction PIN every money movement needs, as on MoMo.
 * Three wrong tries lock it for 30 minutes; every attempt is audited.
 */
class Pins
{
    public const LENGTH = 5;

    public const MAX_ATTEMPTS = 3;

    public const LOCK_MINUTES = 30;

    // Recently set: risky operations need a step-up for a while.
    public const FRESH_HOURS = 24;

    public function has(User $user): bool
    {
        return TransactionPin::query()->where('user_id', $user->id)->exists();
    }

    public function set(User $user, string $pin): void
    {
        if (! preg_match('/^\d{'.self::LENGTH.'}$/', $pin) || $this->isWeak($pin)) {
            throw new InjongePayException(__('injongepay::injongepay.err_pin_weak', ['length' => self::LENGTH]));
        }

        $had = $this->has($user);
        TransactionPin::query()->updateOrCreate(['user_id' => $user->id], [
            'pin_hash' => Hash::make($pin), 'failed_attempts' => 0, 'locked_until' => null, 'set_at' => now(),
        ]);

        audit($had ? 'injongepay_pin_changed' : 'injongepay_pin_set', __('injongepay::audit.'.($had ? 'injongepay_pin_changed' : 'injongepay_pin_set')).': '.$user->name, $user, [], $user->business_id);
        \Modules\InjongePay\Support\BusinessAlert::send((int) $user->business_id, __('injongepay::injongepay.alert_pin_'.($had ? 'changed' : 'set')),
            __('injongepay::injongepay.alert_pin_body', ['name' => $user->name]), route('injongepay.security', [], false), $user);
    }

    /** @throws InjongePayException when wrong or locked */
    public function check(User $user, ?string $pin): void
    {
        $row = TransactionPin::query()->where('user_id', $user->id)->first();

        if (! $row) {
            throw new InjongePayException(__('injongepay::injongepay.err_pin_not_set'));
        }

        if ($row->locked_until?->isFuture()) {
            throw new InjongePayException(__('injongepay::injongepay.err_pin_locked', ['minutes' => (int) ceil(now()->diffInMinutes($row->locked_until, true))]));
        }

        if ($pin !== null && Hash::check($pin, $row->pin_hash)) {
            $row->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();

            return;
        }

        $attempts = $row->failed_attempts + 1;
        $locked = $attempts >= self::MAX_ATTEMPTS;
        $row->forceFill(['failed_attempts' => $locked ? 0 : $attempts, 'locked_until' => $locked ? now()->addMinutes(self::LOCK_MINUTES) : null])->save();

        if ($locked) {
            \Modules\InjongePay\Support\BusinessAlert::send((int) $user->business_id, __('injongepay::injongepay.alert_pin_locked'),
                __('injongepay::injongepay.alert_pin_locked_body', ['name' => $user->name, 'minutes' => self::LOCK_MINUTES]), route('injongepay.security', [], false), $user);
        }

        audit($locked ? 'injongepay_pin_locked' : 'injongepay_pin_wrong', __('injongepay::audit.'.($locked ? 'injongepay_pin_locked' : 'injongepay_pin_wrong')).': '.$user->name, $user, ['attempt' => $attempts], $user->business_id);

        throw new InjongePayException($locked ? __('injongepay::injongepay.err_pin_locked', ['minutes' => self::LOCK_MINUTES]) : __('injongepay::injongepay.err_pin_wrong', ['left' => self::MAX_ATTEMPTS - $attempts]));
    }

    public function isFresh(User $user): bool
    {
        $setAt = TransactionPin::query()->where('user_id', $user->id)->value('set_at');

        return $setAt !== null && now()->subHours(self::FRESH_HOURS)->lt($setAt);
    }

    private function isWeak(string $pin): bool
    {
        return count(array_unique(str_split($pin))) === 1 || str_contains('0123456789', $pin) || str_contains('9876543210', $pin);
    }
}
