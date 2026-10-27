<?php

namespace Modules\Frontend\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Frontend\Models\Message;

/**
 * Website contact messages of the signed-in business (scoped by
 * BelongsToBusiness).
 */
class MessageController extends Controller
{
    public function index(): View
    {
        return view('frontend::admin.messages.index', [
            'messages' => Message::query()->latest('id')->paginate(20),
        ]);
    }

    public function show(Message $message): View
    {
        if (! $message->read_at) {
            $message->forceFill(['read_at' => now()])->save();
        }

        return view('frontend::admin.messages.show', ['message' => $message]);
    }

    public function destroy(Message $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('frontend.admin.messages.index')->with('success', __('body.contact_message_deleted'));
    }
}
