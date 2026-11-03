<?php

namespace Modules\InjongePay\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\InjongePay\Models\BusinessProfile;
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
}
