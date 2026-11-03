<?php

namespace Modules\InjongePay\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\Wallet;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $payments = Payment::query()->with('wallet')
            ->when($request->query('wallet'), fn ($q, $uuid) => $q->whereIn('wallet_id', Wallet::query()->where('uuid', $uuid)->select('id')))
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
}
