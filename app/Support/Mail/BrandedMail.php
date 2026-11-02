<?php

namespace App\Support\Mail;

use App\Libraries\ColorLibrary;
use App\Models\Business\Business;
use App\Services\Branding\BrandingService;
use App\Support\Branding\Fonts;
use App\Support\Branding\ThemeTokens;
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
        // The From name: the caller's, else the business's chosen sender name, else its name.
        $fromName = $senderName ?? $brand['sender'] ?? $brand['name'];
        if ($senderName) {
            $brand['name'] = $senderName;
        }

        $message = (new MailMessage)
            ->subject($subject)
            ->from((string) config('mail.from.address'), $fromName)
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

        $replyTo ??= $brand['reply_to'] ?? $business?->contactEmail();
        if ($replyTo) {
            $message->replyTo($replyTo, $fromName);
        }

        return $message;
    }

    /**
     * How a business's emails look (Branding → Emails, docs/branding.md §3.10).
     * $overrides: unsaved form values, for the live preview only.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function brand(?Business $business, array $overrides = []): array
    {
        $settings = $business?->exists ? app(BrandingService::class)->emailSettings($business) : [];
        $settings = array_replace($settings, array_filter($overrides, fn ($value) => $value !== null));
        $color = $business ? $business->brandColor() : Business::DEFAULT_BRAND_COLOR;
        $header = ColorLibrary::parse($settings['email_header_color'] ?? null) ?? $color;
        $darkHeader = ColorLibrary::isDark($header);
        $brand = $business?->exists ? app(BrandingService::class)->brand($business) : null;
        $darkLogo = $business?->exists && $darkHeader ? app(BrandingService::class)->darkLogoUrl($business) : null;
        $logo = $darkLogo ?? $business?->logoUrl();

        return [
            // Many mail apps ignore web fonts: the stack falls back to safe ones.
            'font' => $business ? app(BrandingService::class)->font($business) : Fonts::DEFAULT,
            'name' => $business ? $business->companyName() : (string) config('app.name'),
            'color' => $color,
            'button_ink' => ThemeTokens::textOn($color),
            'header' => $header,
            'header_ink' => ThemeTokens::textOn($header),
            'logo' => $logo ? url($logo) : null,
            'sender' => $settings['email_sender_name'] ?? null,
            'reply_to' => $settings['email_reply_to'] ?? null,
            'footer' => $settings['email_footer'] ?? null,
            'signature' => $settings['email_signature'] ?? null,
            'contact' => $brand && ! empty($settings['email_show_contact'])
                ? array_values(array_filter([$brand->phone, $brand->email, $brand->website, $brand->address ? str_replace("\n", ', ', $brand->address) : null]))
                : [],
        ];
    }
}
