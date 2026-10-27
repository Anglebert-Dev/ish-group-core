<?php

namespace App\Support\Mail;

use App\Models\Business\Business;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Every email the platform sends goes through here so it carries the right
 * name: a business's emails show the business as the sender (and replies go
 * to the business), platform emails show the platform.
 *
 * The SMTP account (MAIL_FROM_ADDRESS) stays the sending address — mail
 * providers only allow their own address — only the name and reply-to change.
 */
class BrandedMail
{
    /**
     * @param  list<string>  $lines  paragraphs (plain text)
     */
    public static function make(
        ?Business $business,
        string $subject,
        array $lines,
        ?string $actionText = null,
        ?string $actionUrl = null,
        array $outro = [],
        ?string $replyTo = null,
        ?string $senderName = null,
    ): MailMessage {
        $brand = self::brand($business);
        if ($senderName) {
            $brand['name'] = $senderName;
        }

        $message = (new MailMessage)
            ->subject($subject)
            ->from((string) config('mail.from.address'), $brand['name'])
            ->view('emails.message', [
                'brand' => $brand,
                'subject' => $subject,
                'lines' => $lines,
                'actionText' => $actionText,
                'actionUrl' => $actionUrl,
                'outro' => $outro,
            ]);

        // Keep the standard fields filled too (logs, tests, plain-text clients).
        $message->actionText = $actionText;
        $message->actionUrl = $actionUrl;
        $message->introLines = $lines;
        $message->outroLines = $outro;

        $replyTo ??= $business?->contactEmail();
        if ($replyTo) {
            $message->replyTo($replyTo, $brand['name']);
        }

        return $message;
    }

    /**
     * @return array{name: string, color: string, logo: ?string}
     */
    public static function brand(?Business $business): array
    {
        return [
            'name' => $business ? $business->companyName() : (string) config('app.name'),
            'color' => $business ? $business->brandColor() : Business::DEFAULT_BRAND_COLOR,
            'logo' => $business?->logoUrl() ? url($business->logoUrl()) : null,
        ];
    }
}
