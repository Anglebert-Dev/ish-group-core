<?php

namespace Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Notifications\BrandedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Modules\Frontend\Models\Message;
use Modules\Frontend\Support\SiteContext;

/**
 * Public contact form: saved for the business, emailed to it, and the
 * visitor gets a confirmation in the business's name.
 */
class ContactController extends Controller
{
    public function store(Request $request, SiteContext $context): RedirectResponse
    {
        // Honeypot: real visitors never fill the hidden "website" field.
        if (filled($request->input('website'))) {
            return back()->with('success', __('body.contact_sent'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $site = $context->site();
        $business = $site->business;

        $message = Message::query()->create($data + [
            'business_id' => $business->id,
            'ip_address' => $request->ip(),
        ]);

        $to = $site->get('contact.email') ?: $business->contactEmail();

        if ($to) {
            Notification::route('mail', $to)->notify(new BrandedNotification(
                $business,
                __('body.contact_email_subject', ['name' => $data['name']]),
                array_filter([
                    $data['subject'] ?? null,
                    $data['message'],
                    __('body.contact_email_from', ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? '—']),
                ]),
                __('body.contact_email_action'),
                route('frontend.admin.messages.index'),
                [],
                $data['email'],
            ));
        }

        Notification::route('mail', $data['email'])->notify(new BrandedNotification(
            $business,
            __('body.contact_confirm_subject', ['company' => $business->companyName()]),
            [__('body.contact_confirm_line', ['name' => $data['name']]), $data['message']],
        ));

        return back()->with('success', __('body.contact_sent'));
    }
}
