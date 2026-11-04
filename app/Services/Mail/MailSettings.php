<?php

namespace App\Services\Mail;

use App\Models\System\System;
use App\Services\BaseService;
use App\Services\Install\MailProbe;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * The SMTP account the platform sends from, set by a superadmin in Platform
 * settings → Email. The whole record is encrypted in the system table and wins
 * over MAIL_* in .env; with nothing saved, .env is used as before.
 *
 * It is read when mail is first needed, not on every request, and again before
 * each job so a running queue worker picks up a change (AppServiceProvider).
 */
class MailSettings extends BaseService
{
    public const KEY = 'mail_settings';

    private const FIELDS = ['host', 'port', 'scheme', 'username', 'password', 'from_address', 'from_name'];

    /** @var array{default: mixed, smtp: mixed, from: mixed}|null what .env gave, to go back to */
    private ?array $server = null;

    /** Fingerprint of what is applied now; null = the .env settings. */
    private ?string $applied = null;

    public function __construct(
        protected Application $app,
        protected MailProbe $probe,
    ) {}

    /**
     * @return array<string, mixed>|null null with nothing saved, or when it can't be read
     */
    public function saved(): ?array
    {
        try {
            $stored = System::getProperty(self::KEY);

            return $stored ? json_decode(Crypt::decryptString($stored), true) : null;
        } catch (Throwable) {
            // No database yet (before install) or a changed APP_KEY: use .env.
            return null;
        }
    }

    /** Saved settings exist but this server can't decrypt them (a changed APP_KEY). */
    public function unreadable(): bool
    {
        return $this->saved() === null && System::getProperty(self::KEY) !== null;
    }

    /** Make the live mail config match what is saved. Safe to call repeatedly. */
    public function apply(): void
    {
        $saved = $this->saved();
        $fingerprint = $saved ? hash('sha256', (string) json_encode($saved)) : null;

        if ($fingerprint === $this->applied) {
            return;
        }

        $this->server ??= [
            'default' => config('mail.default'),
            'smtp' => config('mail.mailers.smtp'),
            'from' => config('mail.from'),
        ];
        $previous = config('mail.default');

        config($saved ? [
            'mail.default' => 'smtp',
            // url: a MAIL_URL in .env would otherwise overrule these details.
            'mail.mailers.smtp' => array_merge((array) $this->server['smtp'], MailProbe::mailerConfig($saved), ['url' => null]),
            'mail.from' => ['address' => $saved['from_address'], 'name' => ($saved['from_name'] ?? null) ?: config('app.name')],
        ] : [
            'mail.default' => $this->server['default'],
            'mail.mailers.smtp' => $this->server['smtp'],
            'mail.from' => $this->server['from'],
        ]);

        $this->applied = $fingerprint;

        if ($this->app->resolved('mail.manager')) {
            $manager = $this->app->make('mail.manager');
            $manager->purge('smtp');
            $manager->purge((string) $previous);
        }
    }

    /**
     * What the Email tab shows. The password itself never leaves the server.
     *
     * @return array<string, mixed>
     */
    public function form(): array
    {
        $this->apply();
        $smtp = config('mail.default') === 'smtp';

        return [
            'host' => $smtp ? config('mail.mailers.smtp.host') : null,
            'port' => $smtp ? config('mail.mailers.smtp.port') : 587,
            'scheme' => $smtp ? config('mail.mailers.smtp.scheme') : null,
            'username' => $smtp ? config('mail.mailers.smtp.username') : null,
            'from_address' => $smtp ? config('mail.from.address') : null,
            'from_name' => $smtp ? config('mail.from.name') : null,
            'has_password' => (bool) $this->currentPassword(),
            'source' => $this->saved() ? 'saved' : 'server',
            'unreadable' => $this->unreadable(),
        ];
    }

    /**
     * Save the details. With $testTo, a test email goes out first using the
     * details as typed, and nothing changes unless the mail server accepts it.
     *
     * @param  array<string, mixed>  $input
     */
    public function save(array $input, ?string $testTo = null): array
    {
        $before = $this->form();
        $mail = array_intersect_key($input, array_flip(self::FIELDS)) + array_fill_keys(self::FIELDS, null);
        $mail['password'] = $this->passwordFor($mail, $before);

        if ($mail['password'] === false) {
            return $this->failure(__('body.mail_password_needed'));
        }

        if ($testTo) {
            $test = $this->probe->send($mail, $testTo);

            if (! $test['ok']) {
                $this->audit('mail_settings_test_failed', 'Email settings not saved: the test email failed', ['host' => $mail['host'], 'to' => $testTo, 'error' => $test['message']]);

                return $this->failure($test['message']);
            }
        }

        // Written quietly: the audit entry below says what changed, without the ciphertext.
        System::withoutEvents(fn () => System::addProperty(self::KEY, Crypt::encryptString((string) json_encode($mail))));
        $this->apply();

        $changes = [];
        foreach (array_diff(self::FIELDS, ['password']) as $field) {
            if ((string) $before[$field] !== (string) $mail[$field]) {
                $changes[$field] = ['from' => $before[$field], 'to' => $mail[$field]];
            }
        }

        $this->audit('mail_settings_changed', 'Email settings changed', [
            'changes' => $changes,
            'password_changed' => ! empty($input['password']),
            'tested_with' => $testTo,
        ]);

        return $this->success(null, $testTo ? __('body.mail_saved_tested', ['email' => $testTo]) : __('body.mail_saved'));
    }

    /** Forget the saved details and go back to the .env settings. */
    public function reset(): array
    {
        System::withoutEvents(fn () => System::removeProperty(self::KEY));
        $this->apply();

        $this->audit('mail_settings_reset', 'Email settings removed; using the server file again');

        return $this->success(null, __('body.mail_reset_done'));
    }

    /**
     * The password to store: the typed one, or the current one left unchanged.
     * The current one is only ever sent to the mail server it belongs to, so
     * this form can't be used to hand it to a different server. False = must be typed.
     *
     * @param  array<string, mixed>  $mail
     * @param  array<string, mixed>  $before
     */
    private function passwordFor(array $mail, array $before): string|false|null
    {
        if (filled($mail['password'])) {
            return $mail['password'];
        }

        // No username: the server takes mail without signing in.
        if (blank($mail['username'])) {
            return null;
        }

        $current = $this->currentPassword();

        if (! $current) {
            return null;
        }

        return strcasecmp((string) $before['host'], (string) $mail['host']) === 0 ? $current : false;
    }

    private function currentPassword(): ?string
    {
        return config('mail.default') === 'smtp' ? config('mail.mailers.smtp.password') : null;
    }

    /** @param  array<string, mixed>  $details */
    private function audit(string $action, string $description, array $details = []): void
    {
        audit($action, $description, null, $details, (int) System::getProperty('primary_business_id') ?: null);
    }
}
