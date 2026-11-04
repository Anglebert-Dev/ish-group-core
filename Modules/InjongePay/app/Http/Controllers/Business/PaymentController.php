<?php

namespace Modules\InjongePay\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\BankMirror;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $payments = Payment::query()->with('wallet')
            ->when($request->query('wallet'), fn ($q, $uuid) => $q->whereIn('wallet_id', Wallet::query()->whereUuid($uuid)->select('id')))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when(trim((string) $request->query('search')), fn ($q, $s) => $q->where(fn ($w) => $w->where('buyer_phone', 'like', "%{$s}%")
                ->orWhere('buyer_name', 'like', "%{$s}%")->orWhere('bank_reference', 'like', "%{$s}%")->orWhere('reference', 'like', "%{$s}%")))
            ->latest('id')->paginate(30)->withQueryString();

        return view('injongepay::business.payments.index', [
            'payments' => $payments,
            'wallets' => Wallet::query()->whereNull('internal_role')->orderBy('name')->get()->mapWithKeys(fn (Wallet $w) => [$w->uuid => $w->name.' · '.$w->merchant_code])->all(),
        ]);
    }

    public function show(Payment $payment): View
    {
        return view('injongepay::business.payments.show', [
            'payment' => $payment->load('wallet'),
            'entries' => LedgerEntry::query()->where('source_type', $payment->getMorphClass())->where('source_id', $payment->id)->orderBy('id')->get(),
        ]);
    }

    /** Reads GTBank's list for this payment's days now, instead of waiting for the regular read. */
    public function check(Payment $payment, BankMirror $mirror): RedirectResponse
    {
        $day = $payment->value_date ?? $payment->created_at;
        $pulled = $mirror->pull($payment->wallet, $day->copy()->subDay()->startOfDay(), $day->copy()->addDay()->endOfDay());
        $confirmed = $payment->fresh()->cleared_at !== null;

        audit('injongepay_checked_at_bank', __('injongepay::injongepay.audit_checked_at_bank', ['what' => $payment->reference]), $payment, ['answered' => $pulled !== null, 'confirmed' => $confirmed], $payment->business_id);

        return match (true) {
            $pulled === null => back()->with('error', __('injongepay::injongepay.payment_check_no_answer')),
            $confirmed => back()->with('success', __('injongepay::injongepay.payment_check_confirmed')),
            default => back()->with('error', __('injongepay::injongepay.payment_check_not_listed')),
        };
    }
}
