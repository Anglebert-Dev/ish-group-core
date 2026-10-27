<?php

namespace App\Services\SuperAdmin;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\Announcement\Announcement;
use App\Models\Business\Business;
use App\Models\User;
use App\Notifications\BrandedNotification;
use App\Services\BaseService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;

/**
 * Platform announcements to the Admins of selected businesses, in-app
 * and/or by email (queued, sent in the platform's name).
 */
class CommunicatorService extends BaseService
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function send(User $sender, array $data): array
    {
        $recipients = $this->recipients($data['audience'], $data['business_ids'] ?? []);

        if ($recipients->isEmpty()) {
            return $this->failure(__('body.communicator_no_recipients'));
        }

        $announcement = Announcement::query()->create([
            'subject' => $data['subject'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'business_ids' => $data['audience'] === 'selected' ? array_map('intval', $data['business_ids'] ?? []) : null,
            'send_email' => (bool) ($data['send_email'] ?? false),
            'send_in_app' => (bool) ($data['send_in_app'] ?? false),
            'recipients_count' => $recipients->count(),
            'sent_by' => $sender->id,
        ]);

        foreach ($recipients as $user) {
            if ($announcement->send_in_app) {
                $this->notifications->notify($user, $announcement->subject, $announcement->body, NotificationType::Info);
            }

            if ($announcement->send_email) {
                $user->notify(new BrandedNotification(null, $announcement->subject, preg_split('/\R{2,}/', trim($announcement->body)), null, null, [], $sender->email));
            }
        }

        return $this->success($announcement, __('body.communicator_sent', ['count' => $recipients->count()]));
    }

    /**
     * Active Admins of the chosen businesses.
     *
     * @return Collection<int, User>
     */
    public function recipients(string $audience, array $businessIds = []): Collection
    {
        $businesses = Business::query()
            ->when($audience === 'active', fn ($q) => $q->where('is_active', true))
            ->when($audience === 'selected', fn ($q) => $q->whereIn('id', array_map('intval', $businessIds)))
            ->pluck('id');

        return User::query()
            ->whereIn('business_id', $businesses)
            ->where('status', UserStatus::Active->value)
            ->with('roles')
            ->get()
            ->filter(fn (User $user) => $user->isBusinessAdmin())
            ->values();
    }
}
