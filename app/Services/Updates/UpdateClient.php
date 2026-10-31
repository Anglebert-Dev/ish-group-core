<?php

namespace App\Services\Updates;

use App\Models\System\System;
use App\Support\Distribution\PackageSignature;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Talks to our update server (docs/self-hosting-build-guide.md §7). Only
 * believes answers signed with a key in config('distribution.public_keys'),
 * carrying the nonce it sent, its own site id and a current time.
 */
class UpdateClient
{
    /** How far the server's clock may be from ours (seconds). */
    public const CLOCK_SKEW = 600;

    public function linked(): bool
    {
        return filled(System::getProperty('distribution_token')) && filled(System::getProperty('distribution_server_url'));
    }

    public function siteUuid(): string
    {
        return (string) System::getProperty('distribution_site_uuid');
    }

    public function serverUrl(): string
    {
        return rtrim((string) System::getProperty('distribution_server_url'), '/');
    }

    /**
     * @param  array<string, mixed>  $payload  everything but site_uuid and nonce
     * @return array{update_mode: string, poll_minutes: int, offers: list<array<string, mixed>>}
     */
    public function checkIn(array $payload): array
    {
        $nonce = bin2hex(random_bytes(16));

        $response = $this->request()->post($this->serverUrl().'/api/distribution/v1/check-in', $payload + [
            'site_uuid' => $this->siteUuid(),
            'nonce' => $nonce,
        ]);

        if (! $response->successful()) {
            throw new UpdateException(__('updates.server_said', ['status' => $response->status(), 'message' => $response->json('message') ?? '']));
        }

        $body = $response->body();
        $keys = (array) config('distribution.public_keys', []);

        if ($keys === [] || ! PackageSignature::verify(PackageSignature::responseMessage($body), (string) $response->header('X-Ish-Signature'), $keys)) {
            throw new UpdateException(__('updates.bad_signature'));
        }

        $answer = json_decode($body, true);

        if (! is_array($answer)
            || ! hash_equals($nonce, (string) ($answer['nonce'] ?? ''))
            || ! hash_equals($this->siteUuid(), (string) ($answer['site_uuid'] ?? ''))) {
            throw new UpdateException(__('updates.bad_answer'));
        }

        if (abs(time() - (int) ($answer['server_time'] ?? 0)) > self::CLOCK_SKEW) {
            throw new UpdateException(__('updates.clock_skew'));
        }

        return [
            'update_mode' => ($answer['update_mode'] ?? 'manual') === 'auto' ? 'auto' : 'manual',
            'poll_minutes' => (int) ($answer['poll_minutes'] ?? 60),
            'offers' => array_values(array_filter((array) ($answer['offers'] ?? []), fn ($offer) => $this->validOffer($offer))),
        ];
    }

    public function download(array $offer, string $target): void
    {
        try {
            $response = $this->request()->timeout(300)->sink($target)->get($offer['download_url']);
        } catch (Throwable $e) {
            throw new UpdateException(__('updates.download_failed', ['error' => $e->getMessage()]));
        }

        if (! $response->successful()) {
            @unlink($target);
            throw new UpdateException(__('updates.download_failed', ['error' => 'HTTP '.$response->status()]));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    public function report(array $results): void
    {
        $this->request()->post($this->serverUrl().'/api/distribution/v1/report', [
            'site_uuid' => $this->siteUuid(),
            'results' => $results,
        ])->throw();
    }

    /**
     * Shape check, and nothing is fetched from anywhere but our server.
     */
    private function validOffer(mixed $offer): bool
    {
        $neverShip = array_map('strtolower', (array) config('distribution.never_ship', []));

        return is_array($offer)
            && preg_match('/^[A-Z][A-Za-z0-9]{0,63}$/', (string) ($offer['module'] ?? '')) === 1
            && ! in_array(strtolower($offer['module']), $neverShip, true)
            && in_array($offer['action'] ?? null, ['install', 'update'], true)
            && preg_match('/^\d+(\.\d+){0,3}([-+][A-Za-z0-9.]+)?$/', (string) ($offer['version'] ?? '')) === 1
            && preg_match('/^[a-f0-9]{64}$/', (string) ($offer['sha256'] ?? '')) === 1
            && filled($offer['signature'] ?? null)
            && str_starts_with((string) ($offer['download_url'] ?? ''), $this->serverUrl().'/');
    }

    private function request(): PendingRequest
    {
        return Http::withToken(Crypt::decryptString((string) System::getProperty('distribution_token')))
            ->acceptJson()
            ->timeout(30)
            ->withUserAgent('ish-core/'.config('core.version'));
    }
}
