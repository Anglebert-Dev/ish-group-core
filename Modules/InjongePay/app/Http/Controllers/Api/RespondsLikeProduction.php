<?php

namespace Modules\InjongePay\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Modules\InjongePay\Support\PlatformSettings;

/**
 * The {success, message, data|errors} envelope GTBank already receives from
 * the production app, and its shared-secret check (no switch to turn it off).
 */
trait RespondsLikeProduction
{
    protected function sendSuccess(mixed $data = null, string $message = '', int $code = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }

    protected function sendError(string $message, mixed $errors = null, int $code = 400): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $code);
    }

    protected function sendUnauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->sendError($message, null, 401);
    }

    /**
     * The secret header when GTBank sends one (it must then be right), otherwise
     * GTBank's own address. Either way the money stays pending until GTBank's
     * transaction list confirms it, so a forged webhook can never be withdrawn.
     */
    protected function fromGtbank(Request $request): bool
    {
        $sent = $request->header('X-GTBank-Webhook-Secret');

        if (filled($sent)) {
            $expected = PlatformSettings::webhookSecret();

            return filled($expected) && hash_equals((string) $expected, (string) $sent);
        }

        $allowed = PlatformSettings::webhookAllowedIps();

        return $allowed !== [] && IpUtils::checkIp((string) $request->ip(), $allowed);
    }
}
