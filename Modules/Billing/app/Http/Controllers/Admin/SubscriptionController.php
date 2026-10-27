<?php

namespace Modules\Billing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Libraries\ListExportLibrary;
use App\Models\Business\Business;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Package;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\SubscriptionService;
use Modules\Billing\Support\BillingExports;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptions)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'status', 'package']);
        $subscriptions = $this->filtered($filters)->with(['business', 'package'])->paginate(20)->withQueryString();

        return view('billing::admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'filters' => $filters,
            'packages' => Package::withTrashed()->ordered()->pluck('name', 'id'),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $export = BillingExports::subscriptions($this->filtered($request->only(['q', 'status', 'package'])));

        return $format === 'pdf' ? ListExportLibrary::pdf($export) : ListExportLibrary::excel($export);
    }

    private function filtered(array $filters): Builder
    {
        return Subscription::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['package'] ?? null, fn ($q, $package) => $q->where('package_id', $package))
            ->when(trim((string) ($filters['q'] ?? '')) !== '', fn ($q) => $q->whereHas('business', fn ($b) => $b->where('name', 'like', '%'.trim($filters['q']).'%')))
            ->latest('id');
    }

    public function show(Subscription $subscription): View
    {
        return view('billing::admin.subscriptions.show', [
            'subscription' => $subscription->load(['business', 'package', 'scheduledPackage', 'coupon', 'invoices']),
        ]);
    }

    /**
     * Assign (or change) a business's package. Optionally record that it
     * was already paid offline, which activates it immediately.
     */
    public function assign(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:billing_packages,id'],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        $result = $this->subscriptions->subscribe($business, Package::query()->findOrFail($data['package_id']), $data['coupon'] ?? null, true);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function extend(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate(['until' => ['required', 'date', 'after:today']]);

        $result = $this->subscriptions->extend($subscription, Carbon::parse($data['until'])->endOfDay());

        return back()->with('success', $result['message']);
    }

    public function cancel(Subscription $subscription): RedirectResponse
    {
        abort_unless(in_array($subscription->status, SubscriptionStatus::current(), true), 404);

        $result = $this->subscriptions->cancelNow($subscription);

        return back()->with('success', $result['message']);
    }
}
