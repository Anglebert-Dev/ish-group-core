<?php

namespace Modules\Distribution\Notifications;

use App\Models\Business\Business;
use App\Support\Mail\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An email to a self-hosting customer, in our business's name, optionally
 * with files attached (the server-details CSV form).
 */
class CustomerEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $outro
     * @param  array<string, string>  $attachments  file name => contents
     */
    public function __construct(
        public ?int $businessId,
        public string $subject,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public array $outro = [],
        public array $attachments = [],
    ) {
        // Queued: binary files (PDF) are kept as base64 so the job stores safely.
        $this->attachments = array_map('base64_encode', $attachments);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->businessId ? Business::query()->find($this->businessId) : null;
        $message = BrandedMail::make($business, $this->subject, $this->lines, $this->actionText, $this->actionUrl, $this->outro);

        foreach ($this->attachments as $name => $contents) {
            $message->attachData(base64_decode($contents), $name, ['mime' => match (true) {
                str_ends_with($name, '.csv') => 'text/csv', str_ends_with($name, '.pdf') => 'application/pdf', default => 'application/octet-stream'
            }]);
        }

        return $message;
    }
}
