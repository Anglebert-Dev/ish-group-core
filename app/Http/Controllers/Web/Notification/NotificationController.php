<?php

namespace App\Http\Controllers\Web\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification\Notification;
use App\Services\Notification\NotificationService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function index(): View
    {
        return view('app.notifications.index', [
            'notifications' => $this->notifications->listForUser(),
            'unreadCount' => $this->notifications->unreadCount(auth('staff')->user()),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->notifications->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->notifications->exportList());
    }

    public function show(Notification $notification): View|RedirectResponse
    {
        if ((int) $notification->user_id !== (int) auth('staff')->id()) {
            abort(403);
        }

        $this->notifications->markAsRead($notification);

        if ($notification->action_url) {
            return redirect()->to($notification->action_url);
        }

        return view('app.notifications.show', [
            'notification' => $notification->fresh(),
        ]);
    }

    public function markAsRead(Notification $notification): RedirectResponse
    {
        $result = $this->notifications->markAsRead($notification);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function markAllAsRead(): RedirectResponse
    {
        $result = $this->notifications->markAllAsRead();

        return back()->with('success', $result['message']);
    }
}
