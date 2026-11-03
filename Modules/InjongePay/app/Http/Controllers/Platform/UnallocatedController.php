<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\UnallocatedPayment;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\FeeSweep;
use Modules\InjongePay\Services\PaymentIntake;
use Modules\InjongePay\Services\WebhookInbox;
use Modules\InjongePay\Support\Money;

/**
 * Money GTBank credited that no wallet could take. Nothing here is lost: it
 * waits until the platform credits it to a wallet or records how it was
 * settled outside the system.
 */
class UnallocatedController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', UnallocatedPayment::OPEN);

        return view('injongepay::platform.unallocated.index', [
            'rows' => UnallocatedPayment::query()->with(['wallet', 'business'])->where('status', $status)->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'openTotal' => (int) UnallocatedPayment::query()->where('status', UnallocatedPayment::OPEN)->sum('amount'),
            'wallets' => Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereNull('internal_role')->where('is_enabled', true)->with('business')
                ->orderBy('merchant_code')->get()->mapWithKeys(fn (Wallet $w) => [$w->uuid => $w->merchant_code.' · '.$w->name.' · '.$w->business?->name])->all(),
        ]);
    }

    public function allocate(Request $request, UnallocatedPayment $row, PaymentIntake $intake): RedirectResponse
    {
        $data = $request->validate([
            'wallet' => ['required', Rule::exists('ijp_wallets', 'uuid')],
            'note' => ['required', 'string', 'max:500'],
        ]);

        try {
            $payment = $intake->allocate($row, Wallet::query()->withoutGlobalScopes()->whereUuid($data['wallet'])->firstOrFail(), $data['note']);
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.allocated', ['amount' => Money::format($payment->gross_amount), 'ref' => $payment->reference]));
    }

    public function settleOutside(Request $request, UnallocatedPayment $row): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        if ($row->status !== UnallocatedPayment::OPEN) {
            return back()->with('error', __('injongepay::injongepay.err_already_resolved'));
        }

        $row->forceFill([
            'status' => UnallocatedPayment::SETTLED_OUTSIDE, 'resolved_by' => auth()->id(), 'resolved_by_name' => auth()->user()?->name,
            'resolved_at' => now(), 'resolution_note' => $data['note'],
        ])->save();
        audit('injongepay_payment_settled_outside', __('injongepay::injongepay.audit_settled_outside', ['amount' => Money::format($row->amount), 'ref' => $row->bank_reference]),
            $row, ['note' => $data['note']], $row->business_id);

        return back()->with('success', __('injongepay::injongepay.settled_outside'));
    }

    public function processInbox(WebhookInbox $inbox): RedirectResponse
    {
        return back()->with('success', __('injongepay::injongepay.inbox_processed', ['count' => $inbox->retryPending()]));
    }

    public function sweepFees(FeeSweep $sweep): RedirectResponse
    {
        return back()->with('success', __('injongepay::injongepay.fees_swept', ['amount' => Money::format($sweep->run())]));
    }
}
