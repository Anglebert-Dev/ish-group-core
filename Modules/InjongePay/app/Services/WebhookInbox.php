<?php

namespace Modules\InjongePay\Services;

use Modules\InjongePay\Gtbank\BankPayloads;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\WebhookEvent;
use Throwable;

/**
 * Stores each GTBank webhook before acting on it. A redelivery of the same
 * event is recognised by its key; an event that failed is retried by the
 * scheduler (injongepay:process-inbox) until it succeeds.
 */
class WebhookInbox
{
    public function __construct(protected PaymentIntake $intake)
    {
    }

    public function store(string $kind, string $key, array $payload, ?string $ip): WebhookEvent
    {
        $event = WebhookEvent::query()->firstOrCreate(['event_key' => "{$kind}:{$key}"], ['kind' => $kind, 'payload' => $payload, 'ip' => $ip]);

        if (! $event->wasRecentlyCreated) {
            $event->increment('deliveries');
        }

        return $event;
    }

    /**
     * @return array{outcome: string, subject: mixed}
     */
    public function processPayment(WebhookEvent $event): array
    {
        $status = BankPayloads::transaction($event->payload)['status'];

        try {
            [$outcome, $subject] = match ($status) {
                'SUCCESS' => $this->intake->receive($event->payload, Payment::VIA_WEBHOOK),
                // Recorded for the bank mirror; nothing is credited until GTBank says SUCCESS.
                default => ['ignored', null],
            };
        } catch (Throwable $e) {
            $event->forceFill(['attempts' => $event->attempts + 1, 'last_error' => mb_substr($e->getMessage(), 0, 2000), 'outcome' => 'failed'])->save();

            throw $e;
        }

        $event->forceFill([
            'processed_at' => now(), 'attempts' => $event->attempts + 1, 'outcome' => $outcome, 'last_error' => null,
            'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
        ])->save();

        return ['outcome' => $outcome, 'subject' => $subject];
    }

    /** Events not processed yet (a crash, a database hiccup): the scheduler's twin of a redelivery. */
    public function retryPending(): int
    {
        $count = 0;

        foreach (WebhookEvent::query()->whereNull('processed_at')->where('kind', 'payment')->where('created_at', '<', now()->subMinute())->where('attempts', '<', 20)->orderBy('id')->limit(200)->get() as $event) {
            try {
                $this->processPayment($event);
                $count++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $count;
    }
}
