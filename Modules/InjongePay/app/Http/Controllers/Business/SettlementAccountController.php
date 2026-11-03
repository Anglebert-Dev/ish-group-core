<?php

namespace Modules\InjongePay\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Services\Security\Pins;
use Modules\InjongePay\Services\SettlementAccounts;

/** A business's own accounts for cash-outs; adding or removing one needs the PIN. */
class SettlementAccountController extends Controller
{
    public function index(): View
    {
        return view('injongepay::business.settlement-accounts', [
            'accounts' => SettlementAccount::query()->whereIn('status', [SettlementAccount::PENDING, SettlementAccount::APPROVED])->latest('id')->get(),
        ]);
    }

    public function store(Request $request, SettlementAccounts $accounts, Pins $pins): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:bank,wallet'],
            'account' => ['required', 'string', 'max:20', 'regex:/^[0-9+ ]+$/'],
            'holder_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'pin' => ['required', 'string'],
        ]);

        try {
            $pins->check($request->user('staff'), $data['pin']);
            $accounts->add($request->user('staff')->business, $data);
        } catch (InjongePayException $e) {
            return back()->withInput($request->except('pin'))->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.account_added'));
    }

    public function verify(SettlementAccount $account, SettlementAccounts $accounts): RedirectResponse
    {
        try {
            $accounts->verify($account);
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.account_added'));
    }

    public function destroy(Request $request, SettlementAccount $account, SettlementAccounts $accounts, Pins $pins): RedirectResponse
    {
        try {
            $pins->check($request->user('staff'), $request->input('pin'));
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        $accounts->disable($account, 'Removed by the business');

        return back()->with('success', __('injongepay::injongepay.account_removed'));
    }
}
