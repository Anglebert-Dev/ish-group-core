<?php

namespace Modules\InjongePay\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    protected function secretMatches(Request $request): bool
    {
        $expected = PlatformSettings::webhookSecret();

        return filled($expected) && hash_equals((string) $expected, (string) $request->header('X-GTBank-Webhook-Secret'));
    }
}
