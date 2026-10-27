<?php

namespace App\Services\Auth;

use App\Services\ActivityLog\ActivityLogService;
use App\Enums\ActivityLogAction;
use App\Libraries\TotpLibrary;
use App\Models\User;
use App\Repositories\User\UserRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffMfaService extends BaseService
{
    private const RECOVERY_CODE_COUNT = 8;

    /** Excludes visually-ambiguous characters (0/O, 1/I/L). */
    private const RECOVERY_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(protected UserRepository $users)
    {
    }

    public function beginSetup(User $user): array
    {
        $secret = TotpLibrary::generateSecret();
        $user->forceFill([
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_enabled' => false,
            'mfa_recovery_codes' => null,
        ]);
        $this->users->save($user);

        return $this->success([
            'secret' => $secret,
            'otpauth_url' => TotpLibrary::otpAuthUrl(
                $secret,
                $user->email,
                (string) config('app.name', 'ISH Core')
            ),
        ], __('body.mfa_setup_title'));
    }

    public function confirmSetup(User $user, string $code): array
    {
        $secret = $this->plainSecret($user);

        if ($secret === null || ! TotpLibrary::verify($secret, $code)) {
            return $this->failure(__('body.mfa_invalid'));
        }

        $user->forceFill(['mfa_enabled' => true]);
        $this->users->save($user);

        $recoveryCodes = $this->generateRecoveryCodes($user);
        app(ActivityLogService::class)->log(ActivityLogAction::MfaEnabled, __('body.audit_log_mfa_enabled', ['name' => $user->name]), $user);

        return $this->success(['user' => $user, 'recovery_codes' => $recoveryCodes], __('body.mfa_enabled'));
    }

    public function disable(User $user): array
    {
        $user->forceFill([
            'mfa_secret' => null,
            'mfa_enabled' => false,
            'mfa_recovery_codes' => null,
        ]);
        $this->users->save($user);
        app(ActivityLogService::class)->log(ActivityLogAction::MfaDisabled, __('body.audit_log_mfa_disabled', ['name' => $user->name]), $user);

        return $this->success($user, __('body.mfa_disabled'));
    }

    public function verify(User $user, string $code): bool
    {
        $secret = $this->plainSecret($user);

        return $secret !== null && TotpLibrary::verify($secret, $code);
    }

    public function isEnabled(User $user): bool
    {
        return (bool) $user->mfa_enabled && filled($user->mfa_secret);
    }

    /**
     * Replace this user's recovery codes with a freshly generated set,
     * returning the plaintext codes — the only moment they're ever
     * available, since only their hashes are persisted.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(User $user): array
    {
        $plainCodes = [];
        $stored = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $raw = $this->randomRecoveryCode();
            $plainCodes[] = $this->formatRecoveryCodeForDisplay($raw);
            $stored[] = ['hash' => Hash::make($raw), 'used_at' => null];
        }

        $user->forceFill(['mfa_recovery_codes' => Crypt::encryptString(json_encode($stored))]);
        $this->users->save($user);

        return $plainCodes;
    }

    public function recoveryCodesRemaining(User $user): int
    {
        return collect($this->storedRecoveryCodes($user))
            ->whereNull('used_at')
            ->count();
    }

    /**
     * Check a recovery code and, if valid and unused, consume it so it
     * can never be reused.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $normalized = Str::of($code)->upper()->replaceMatches('/[^A-Z0-9]/', '')->toString();

        if ($normalized === '') {
            return false;
        }

        $codes = $this->storedRecoveryCodes($user);

        foreach ($codes as $index => $entry) {
            if ($entry['used_at'] !== null) {
                continue;
            }

            if (Hash::check($normalized, $entry['hash'])) {
                $codes[$index]['used_at'] = now()->toDateTimeString();
                $user->forceFill(['mfa_recovery_codes' => Crypt::encryptString(json_encode($codes))]);
                $this->users->save($user);

                return true;
            }
        }

        return false;
    }

    private function plainSecret(User $user): ?string
    {
        if (! filled($user->mfa_secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($user->mfa_secret);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{hash: string, used_at: ?string}>
     */
    private function storedRecoveryCodes(User $user): array
    {
        if (! filled($user->mfa_recovery_codes)) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($user->mfa_recovery_codes), true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function randomRecoveryCode(int $length = 10): string
    {
        $alphabet = self::RECOVERY_CODE_ALPHABET;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    private function formatRecoveryCodeForDisplay(string $raw): string
    {
        return substr($raw, 0, 5).'-'.substr($raw, 5, 5);
    }
}
