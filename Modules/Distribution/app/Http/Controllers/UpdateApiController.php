<?php

namespace Modules\Distribution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Distribution\PackageSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Distribution\Models\Release;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Services\ReleaseService;
use Modules\Distribution\Services\SigningKeys;
use Modules\Distribution\Services\UpdateOfferService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The API customer servers call (docs/self-hosting-build-guide.md §7).
 * Authenticated by AuthenticateSite; no session, no CSRF.
 */
class UpdateApiController extends Controller
{
    public function checkIn(Request $request, UpdateOfferService $offers, SigningKeys $keys): Response|JsonResponse
    {
        $data = $request->validate([
            'site_uuid' => ['required', 'string'],
            'nonce' => ['required', 'string', 'min:16', 'max:128'],
            'core_version' => ['nullable', 'string', 'max:64'],
            'php_version' => ['nullable', 'string', 'max:32'],
            'db_driver' => ['nullable', 'string', 'max:32'],
            'app_url' => ['nullable', 'string', 'max:255'],
            'modules_writable' => ['boolean'],
            'modules' => ['array', 'max:200'],
            'modules.*.name' => ['required', 'string', 'max:100'],
            'modules.*.installed_version' => ['nullable', 'string', 'max:32'],
            'modules.*.enabled' => ['boolean'],
        ]);

        if (! $keys->configured()) {
            return response()->json(['message' => 'Updates are not available right now.'], 503);
        }

        $site = $this->site($request);

        $body = json_encode([
            'site_uuid' => $site->uuid,
            'nonce' => $data['nonce'],
            'server_time' => time(),
            'update_mode' => $site->update_mode->value,
            'poll_minutes' => UpdateOfferService::POLL_MINUTES,
            'offers' => $offers->checkIn($site, collect($data)->except(['site_uuid', 'nonce'])->all(), $request->ip()),
            // The site's plan: modules given are announced there, modules taken away are switched off.
            'assigned' => $site->moduleNames(),
        ], JSON_UNESCAPED_SLASHES);

        // Signed, so a site only acts on answers that really come from us.
        return response($body, 200, [
            'Content-Type' => 'application/json',
            'X-Ish-Signature' => $keys->sign(PackageSignature::responseMessage($body)),
        ]);
    }

    public function download(Request $request, Release $release, ReleaseService $releases): BinaryFileResponse|JsonResponse
    {
        $site = $this->site($request);
        $assigned = $site->modules()->where('module', $release->module)->exists();

        if (! $assigned || $release->yanked_at || ! $release->published_at || ! is_file($releases->absolutePath($release))) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->download($releases->absolutePath($release), "{$release->module}-{$release->version}.zip", [
            'X-Ish-Sha256' => $release->sha256,
        ]);
    }

    public function report(Request $request, UpdateOfferService $offers): JsonResponse
    {
        $data = $request->validate([
            'site_uuid' => ['required', 'string'],
            'results' => ['required', 'array', 'max:100'],
            'results.*.module' => ['required', 'string', 'max:100'],
            'results.*.action' => ['required', 'in:install,update,disable,enable'],
            'results.*.from_version' => ['nullable', 'string', 'max:32'],
            'results.*.to_version' => ['required', 'string', 'max:32'],
            'results.*.status' => ['required', 'in:success,failed,rolled_back'],
            'results.*.error' => ['nullable', 'string', 'max:5000'],
            'results.*.duration_ms' => ['nullable', 'integer', 'min:0'],
        ]);

        $offers->report($this->site($request), $data['results']);

        return response()->json(['ok' => true]);
    }

    private function site(Request $request): Site
    {
        return $request->attributes->get('distribution_site');
    }
}
