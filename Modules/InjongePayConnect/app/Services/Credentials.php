<?php

namespace Modules\InjongePayConnect\Services;

use App\Models\Business\Business;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\InjongePayConnect\Models\Connection;
use Modules\InjongePayConnect\Models\Integrator;
use Modules\InjongePayConnect\Models\Token;

/**
 * Client id/secret per connection (the secret is shown once), and bearer
 * tokens: random, stored only as sha256, valid an hour. Rotating or switching
 * off a connection ends every token at once.
 */
class Credentials
{
    /** @return array{0: Connection, 1: string} the connection and its secret, shown once */
    public function create(Integrator $integrator, Business $business): array
    {
        $secret = Str::random(48);
        $connection = new Connection(['integrator_id' => $integrator->id, 'client_id' => 'injp_'.Str::lower(Str::random(24)), 'is_enabled' => true]);
        $connection->forceFill(['business_id' => $business->id, 'client_secret_hash' => Hash::make($secret)])->save();

        audit('injongepay_connection_created', __('injongepayconnect::connect.audit_created', ['integrator' => $integrator->name, 'business' => $business->name]),
            $connection, ['client_id' => $connection->client_id], $business->id);

        return [$connection, $secret];
    }

    public function rotate(Connection $connection): string
    {
        $secret = Str::random(48);
        $connection->forceFill(['client_secret_hash' => Hash::make($secret)])->save();
        Token::query()->where('connection_id', $connection->id)->delete();
        audit('injongepay_connection_rotated', __('injongepayconnect::connect.audit_rotated', ['client' => $connection->client_id]), $connection, [], $connection->business_id);

        return $secret;
    }

    public function setEnabled(Connection $connection, bool $enabled): void
    {
        $connection->forceFill(['is_enabled' => $enabled])->save();

        if (! $enabled) {
            Token::query()->where('connection_id', $connection->id)->delete();
        }

        audit($enabled ? 'injongepay_connection_enabled' : 'injongepay_connection_disabled', __('injongepayconnect::connect.audit_'.($enabled ? 'enabled' : 'disabled'), ['client' => $connection->client_id]),
            $connection, [], $connection->business_id);
    }

    /** @return array{access_token: string, expires_in: int}|null */
    public function issue(string $clientId, string $secret): ?array
    {
        $connection = Connection::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('client_id', $clientId)->where('is_enabled', true)->with(['integrator', 'business'])->first();

        if (! $connection || ! Hash::check($secret, $connection->client_secret_hash) || ! $this->live($connection)) {
            return null;
        }

        $plain = Str::random(64);
        $ttl = (int) config('injongepayconnect.token_ttl_seconds', 3600);
        Token::query()->create(['connection_id' => $connection->id, 'business_id' => $connection->business_id, 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addSeconds($ttl)]);
        $connection->forceFill(['last_used_at' => now()])->saveQuietly();

        return ['access_token' => $plain, 'expires_in' => $ttl];
    }

    public function resolve(string $plain): ?Connection
    {
        $token = Token::query()->where('token_hash', hash('sha256', $plain))->where('expires_at', '>', now())->first();

        if (! $token) {
            return null;
        }

        $connection = Connection::query()->withoutGlobalScopes()->whereNull('deleted_at')->with(['integrator', 'business'])->find($token->connection_id);

        return $connection && $connection->is_enabled && $this->live($connection) ? $connection : null;
    }

    public function prune(): int
    {
        return Token::query()->where('expires_at', '<', now()->subDay())->delete();
    }

    private function live(Connection $connection): bool
    {
        return $connection->integrator?->is_enabled && $connection->business?->is_active && $connection->business->canUseModule('InjongePayConnect');
    }
}
