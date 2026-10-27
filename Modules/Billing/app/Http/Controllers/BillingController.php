<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Entitlements\Quota;
use App\Services\Module\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Http\Requests\SubmitPaymentRequest;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Package;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\CouponService;
use Modules\Billing\Services\InvoiceService;
use Modules\Billing\Services\PaymentService;
use Modules\Billing\Services\SubscriptionService;
use Modules\Billing\Support\BillingEntitlements;
use Modules\Billing\Support\BillingSettings;
use Modules\Billing\Support\InvoicePdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed-in business's own billing: plan, usage, invoices, paying.
 */
class BillingController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptions,
        protected InvoiceService $invoices,
        protected PaymentService $payments,
        protected CouponService $coupons,
    ) {
    }

    public function index(Quota $quota, ModuleManager $modules): View
    {
        $business = $this->business();
        $subscription = $this->subscriptions->current($business);

        $usage = collect($quota->keys())->map(fn (string $key) => [
            'label' => __('body.quota_'.$key) !== 'body.quota_'.$key ? __('body.quota_'.$key) : $key,
            'used' => $quota->used($business, $key),
            'limit' => $quota->limit($business, $key),
        ]);

        return view('billing::business.index', [
            'business' => $business,
            'subscription' => $subscription,
            'isPlatformOwner' => app(BillingEntitlements::class)->isPlatformOwner($business),
            'usage' => $usage,
            'moduleNames' => collect($modules->catalogue())->where('active', true)->reject(fn ($m) => $modules->isBillingExempt($m['name']))->pluck('name'),
            'openInvoices' => Invoice::query()->where('business_id', $business->id)->where('status', InvoiceStatus::Open->value)->latest('id')->get(),
            'invoiceHistory' => Invoice::query()->where('business_id', $business->id)->where('status', '!=', InvoiceStatus::Open->value)->latest('id')->limit(20)->get(),
            'payments' => Payment::query()->with('invoice')->where('business_id', $business->id)->latest('id')->limit(20)->get(),
            'account' => $this->invoices->account($business),
            'instructions' => BillingSettings::get('payment_instructions'),
        ]);
    }

    public function plans(): View
    {
        $business = $this->business();

        return view('billing::business.plans', [
            'packages' => Package::query()->ordered()->get()->filter(fn (Package $p) => $p->isAvailableTo($business))->values(),
            'subscription' => $this->subscriptions->current($business),
        ]);
    }

    public function checkout(Request $request, Package $package): View|RedirectResponse
    {
        $business = $this->business();
        abort_unless($package->isAvailableTo($business), 404);

        $couponResult = $this->coupons->validate($request->query('coupon'), $business, $package);

        return view('billing::business.checkout', [
            'package' => $package,
            'preview' => $this->subscriptions->preview($business, $package, $couponResult['status'] ? $couponResult['data'] : null),
            'couponCode' => $request->query('coupon'),
            'couponError' => $couponResult['status'] ? null : $couponResult['message'],
        ]);
    }

    public function subscribe(Request $request, Package $package): RedirectResponse
    {
        $result = $this->subscriptions->subscribe($this->business(), $package, $request->input('coupon'));

        if (! $result['status']) {
            return back()->with('error', $result['message']);
        }

        // An invoice to pay? Open it straight away.
        if ($result['data'] instanceof Invoice) {
            return redirect()->route('billing.invoices.show', $result['data'])->with('success', $result['message']);
        }

        $open = Invoice::query()->where('business_id', $this->business()->id)->where('status', InvoiceStatus::Open->value)->latest('id')->first();

        return $open
            ? redirect()->route('billing.invoices.show', $open)->with('success', $result['message'])
            : redirect()->route('billing.index')->with('success', $result['message']);
    }

    public function cancel(): RedirectResponse
    {
        $subscription = $this->subscriptions->current($this->business());
        abort_unless($subscription, 404);

        $result = $this->subscriptions->cancel($subscription);

        return redirect()->route('billing.index')->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function resume(): RedirectResponse
    {
        $subscription = $this->subscriptions->current($this->business());
        abort_unless($subscription, 404);

        $result = $this->subscriptions->resume($subscription);

        return redirect()->route('billing.index')->with('success', $result['message']);
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $this->invoices->account($this->business())->fill($data)->save();

        return redirect()->route('billing.index')->with('success', __('billing::billing.account_saved'));
    }

    public function invoice(Invoice $invoice): View
    {
        $this->authorizeInvoice($invoice);

        return view('billing::business.invoice', [
            'invoice' => $invoice->load(['lines', 'payments']),
            'instructions' => BillingSettings::get('payment_instructions'),
        ]);
    }

    public function invoicePdf(Invoice $invoice): Response
    {
        $this->authorizeInvoice($invoice);

        return InvoicePdf::download($invoice);
    }

    public function submitPayment(SubmitPaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        $result = $this->payments->submit(
            $invoice,
            Auth::guard('staff')->user(),
            $request->safe()->except('proof'),
            $request->file('proof'),
        );

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    private function business()
    {
        return Auth::guard('staff')->user()->business;
    }

    private function authorizeInvoice(Invoice $invoice): void
    {
        abort_unless($invoice->business_id === $this->business()->id, 404);
    }
}
