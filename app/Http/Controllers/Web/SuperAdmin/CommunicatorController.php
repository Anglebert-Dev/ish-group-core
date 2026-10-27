<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Announcement\Announcement;
use App\Models\Business\Business;
use App\Services\SuperAdmin\CommunicatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunicatorController extends Controller
{
    public function __construct(protected CommunicatorService $communicator)
    {
    }

    public function index(): View
    {
        return view('app.super-admin.communicator', [
            'businesses' => Business::query()->orderBy('name')->pluck('name', 'id'),
            'history' => Announcement::query()->with('sender')->latest('id')->paginate(10),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', Rule::in(['all', 'active', 'selected'])],
            'business_ids' => ['required_if:audience,selected', 'array'],
            'business_ids.*' => ['integer', 'exists:businesses,id'],
            'send_email' => ['boolean'],
            'send_in_app' => ['boolean'],
        ]);

        if (! $request->boolean('send_email') && ! $request->boolean('send_in_app')) {
            return back()->withInput()->withErrors(['send_in_app' => __('body.communicator_pick_channel')]);
        }

        $result = $this->communicator->send(Auth::guard('staff')->user(), $data + [
            'send_email' => $request->boolean('send_email'),
            'send_in_app' => $request->boolean('send_in_app'),
        ]);

        return $result['status']
            ? redirect()->route('superadmin.communicator.index')->with('success', $result['message'])
            : back()->withInput()->with('error', $result['message']);
    }
}
