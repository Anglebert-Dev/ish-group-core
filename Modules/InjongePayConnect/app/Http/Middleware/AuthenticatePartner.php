<?php

namespace Modules\InjongePayConnect\Http\Middleware;

use App\Support\Tenancy\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Modules\InjongePayConnect\Services\Credentials;
use Symfony\Component\HttpFoundation\IpUtils;

/** Bearer token → the connection (and its business), for one contract only. */
class AuthenticatePartner
{
    public function __construct(protected Credentials $credentials)
    {
    }

    public function handle(Request $request, Closure $next, string $profile)
    {
        $token = (string) $request->bearerToken();
        $connection = $token !== '' ? $this->credentials->resolve($token) : null;

        if (! $connection || $connection->integrator?->profile !== $profile
            || ($connection->allowedIps() !== [] && ! IpUtils::checkIp((string) $request->ip(), $connection->allowedIps()))) {
            return response()->json(['error' => ['code' => 'unauthorized', 'message' => __('injongepayconnect::connect.unauthorized')]], 401);
        }

        $request->attributes->set('partner_connection', $connection);
        app(CurrentBusiness::class)->set($connection->business_id);

        return $next($request);
    }
}
