<?php

namespace Modules\InjongePay\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\InjongePay\Models\BusinessProfile;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\Payment;
use Modules\InjongePay\Models\Wallet;

class OverviewController extends Controller
{
    public function index(): View
    {
        $wallets = Wallet::query()->whereNull('internal_role')->orderBy('name')->get();

        return view('injongepay::business.overview', [
            'wallets' => $wallets,
            'pending' => (int) $wallets->sum('pending_balance'),
            'available' => (int) $wallets->sum('available_balance'),
            'profile' => BusinessProfile::query()->first(),
            'today' => Payment::query()->whereDate('created_at', today())->selectRaw('COUNT(*) AS count, COALESCE(SUM(gross_amount), 0) AS gross')->first(),
        ]);
    }

    /** One merchant account, as the old wallet page: its day, its month, what is on the way out. */
    public function wallet(string $wallet): View
    {
        $wallet = Wallet::query()->whereNull('internal_role')->where('uuid', $wallet)->firstOrFail();
        $totals = fn ($since) => Payment::query()->where('wallet_id', $wallet->id)->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) AS count, COALESCE(SUM(gross_amount), 0) AS gross')->first();

        return view('injongepay::business.wallet', [
            'wallet' => $wallet,
            'today' => $totals(today()),
            'month' => $totals(now()->startOfMonth()),
            'outgoing' => (int) Cashout::query()->where('wallet_id', $wallet->id)->whereIn('status', Cashout::OPEN)->sum('amount'),
            'payments' => Payment::query()->where('wallet_id', $wallet->id)->latest('id')->limit(10)->get(),
            'cashouts' => Cashout::query()->where('wallet_id', $wallet->id)->latest('id')->limit(10)->get(),
        ]);
    }
}
