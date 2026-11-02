<?php

namespace Modules\Distribution\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Distribution\Models\Charge;
use Modules\Distribution\Models\Site;
use Modules\Distribution\Services\CustomerMailer;
use Modules\Distribution\Services\LicenceService;

/**
 * Licence & support on the site page (docs/self-hosting-build-guide.md §11).
 */
class LicenceController extends Controller
{
    public function __construct(protected LicenceService $licences) {}

    public function store(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(Charge::KINDS)],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'support_months' => ['nullable', 'integer', 'min:0', 'max:60'],
            'due_days' => ['nullable', 'integer', 'min:0', 'max:180'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->back($site, $this->licences->charge($site, $data));
    }

    public function pay(Request $request, Site $site, Charge $charge): RedirectResponse
    {
        abort_unless($charge->site_id === $site->id, 404);

        return $this->back($site, $this->licences->markPaid($charge, $request->validate([
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['nullable', Rule::in(Charge::METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:120'],
        ])));
    }

    public function cancel(Request $request, Site $site, Charge $charge): RedirectResponse
    {
        abort_unless($charge->site_id === $site->id, 404);

        return $this->back($site, $this->licences->cancel($charge, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']));
    }

    public function email(Request $request, Site $site, Charge $charge, CustomerMailer $mailer): RedirectResponse
    {
        abort_unless($charge->site_id === $site->id, 404);

        return $this->back($site, $mailer->sendCharge($charge, $request->validate(['email' => ['required', 'email', 'max:255']])['email']));
    }

    /** Set the end date by hand (goodwill, corrections): a reason is required. */
    public function support(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'support_until' => ['nullable', 'date', 'required_without:no_support'],
            'no_support' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        // "No support (until paid)" ticked: the date is cleared whatever was typed.
        $until = $request->boolean('no_support') ? null : ($data['support_until'] ?? null);

        return $this->back($site, $this->licences->setSupportUntil($site, $until, $data['reason']));
    }

    public function remind(Site $site): RedirectResponse
    {
        return $this->back($site, $this->licences->remind($site->load('charges')));
    }

    private function back(Site $site, array $result): RedirectResponse
    {
        return redirect()->to(route('superadmin.distribution.sites.show', $site).'#licence')
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
