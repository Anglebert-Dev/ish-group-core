<?php

namespace App\Services\Install;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one plain test email with the SMTP details being set up.
 */
class MailProbe
{
    /**
     * @param  array{host: string, port: int|string, scheme: ?string, username: ?string, password: ?string, from_address: string, from_name?: ?string}  $mail
     * @return array{ok: bool, message: string}
     */
    public function send(array $mail, string $to): array
    {
        try {
            Mail::build(self::mailerConfig($mail))->raw(__('install.mail_test_body'), function ($message) use ($mail, $to) {
                $message->to($to)
                    ->from($mail['from_address'], $mail['from_name'] ?? null)
                    ->subject(__('install.mail_test_subject'));
            });
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => __('install.mail_failed', ['error' => strtok($e->getMessage(), "\n")])];
        }

        return ['ok' => true, 'message' => __('install.mail_sent', ['email' => $to])];
    }

    /**
     * @param  array{host: string, port: int|string, scheme: ?string, username: ?string, password: ?string}  $mail
     * @return array<string, mixed>
     */
    public static function mailerConfig(array $mail): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => $mail['scheme'] ?: null,
            'host' => $mail['host'],
            'port' => (int) $mail['port'],
            'username' => $mail['username'] ?: null,
            'password' => $mail['password'] ?: null,
            'timeout' => 15,
        ];
    }
}
