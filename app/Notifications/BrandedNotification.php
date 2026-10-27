<?php

namespace App\Notifications;

use App\Models\Business\Business;
use App\Support\Mail\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A queued email in a business's name (or the platform's when $business is
 * null). Use it for any one-off email:
 *
 *   $user->notify(new BrandedNotification($business, 'Subject', ['Line 1']));
 *   Notification::route('mail', $email)->notify(new BrandedNotification(...));
 */
class BrandedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $outro
     */
    public function __construct(
        public ?Business $business,
        public string $subject,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public array $outro = [],
        public ?string $replyTo = null,
        public ?string $senderName = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return BrandedMail::make($this->business, $this->subject, $this->lines, $this->actionText, $this->actionUrl, $this->outro, $this->replyTo, $this->senderName);
    }
}
