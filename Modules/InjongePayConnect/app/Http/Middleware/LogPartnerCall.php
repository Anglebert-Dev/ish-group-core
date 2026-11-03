<?php

namespace Modules\InjongePayConnect\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePayConnect\Models\ApiCall;
use Throwable;

/** Every partner call, the token request included, with secrets hidden. */
class LogPartnerCall
{
    public function handle(Request $request, Closure $next)
    {
        $started = microtime(true);
        $response = $next($request);
        $connection = $request->attributes->get('partner_connection');

        try {
            ApiCall::query()->create([
                'connection_id' => $connection?->id, 'business_id' => $connection?->business_id,
                'method' => $request->method(), 'path' => mb_substr($request->path(), 0, 255), 'status' => $response->getStatusCode(),
                'request' => GtbankClient::redact($request->all()),
                'response' => GtbankClient::redact(json_decode((string) $response->getContent(), true) ?? ['raw' => mb_substr((string) $response->getContent(), 0, 2000)]),
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'caller' => $connection?->business?->name ?? mb_substr((string) $request->input('client_id'), 0, 120),
                'ip' => $request->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }
}
