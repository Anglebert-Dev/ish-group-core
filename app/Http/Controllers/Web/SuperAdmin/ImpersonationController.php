<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SuperAdmin\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function __construct(protected ImpersonationService $impersonation)
    {
    }

    /**
     * {user} is a UUID looked up across all businesses on purpose; the normal
     * {user} binding is limited to the superadmin's own business.
     */
    public function start(Request $request, string $user): RedirectResponse
    {
        $target = User::query()->where('uuid', $user)->firstOrFail();

        $result = $this->impersonation->start($request, Auth::guard('staff')->user(), $target);

        return $result['status']
            ? redirect()->route('dashboard')->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }

    public function leave(Request $request): RedirectResponse
    {
        $result = $this->impersonation->leave($request);

        return $result['status']
            ? redirect()->to($result['data']['return_url'])->with('success', $result['message'])
            : redirect()->route('staff.login')->withErrors(['email' => $result['message']]);
    }
}
