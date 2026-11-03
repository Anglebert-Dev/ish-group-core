<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Exceptions\StepUpRequired;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\Approvals;
use Modules\InjongePay\Services\CashoutService;
use Modules\InjongePay\Services\InternalAccounts;
use Modules\InjongePay\Services\Security\Pins;
use Modules\InjongePay\Services\SettlementAccounts;
use Modules\InjongePay\Support\Money;


class EarningsController extends Controller
{
    public function index(Request $request, InternalAccounts $internal, Pins $pins): View
    {
        $wallets = collect([$internal->platformRevenue(), $internal->bankCut()])->map->fresh();

        return view('injongepay::platform.earnings', [
            'wallets' => $wallets,
            'walletOptions' => $wallets->mapWithKeys(fn (Wallet $w) => [$w->uuid => $w->name.' · '.Money::format($w->available_balance)])->all(),
            'available' => $wallets->mapWithKeys(fn (Wallet $w) => [$w->uuid => intdiv($w->available_balance, 100)])->all(),
            'accounts' => SettlementAccount::query()->whereIn('status', [SettlementAccount::PENDING, SettlementAccount::APPROVED])->latest('id')->get(),
            'cashouts' => Cashout::query()->withoutGlobalScopes()->whereIn('wallet_id', $wallets->pluck('id'))->latest('id')->paginate(20),
            'hasPin' => $pins->has($request->user('staff')),
        ]);
    }

    public function addAccount(Request $request, SettlementAccounts $accounts, Pins $pins): RedirectResponse
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

    public function removeAccount(Request $request, SettlementAccount $account, SettlementAccounts $accounts, Pins $pins): RedirectResponse
    {
        try {
            $pins->check($request->user('staff'), $request->input('pin'));
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        $accounts->disable($account, 'Removed by Inhills');

        return back()->with('success', __('injongepay::injongepay.account_removed'));
    }

    public function cashout(Request $request, Approvals $approvals, CashoutService $cashouts): RedirectResponse
    {
        $data = $request->validate([
            'wallet' => ['required', 'string'],
            'account' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'pin' => ['required', 'string'],
            'code' => ['nullable', 'string', 'max:10'],
        ], ['amount.integer' => __('injongepay::injongepay.err_whole_rwf')]);

        try {
            $wallet = Wallet::query()->whereNotNull('internal_role')->whereUuid($data['wallet'])->firstOrFail();
            $account = SettlementAccount::query()->whereUuid($data['account'])->firstOrFail();
            $amount = Money::toCents((string) $data['amount']);
            $cashouts->validate($wallet, $account, $amount);

            $approval = $approvals->request($request, $request->user('staff'), $wallet, 'cashout', $amount,
                ['settlement_account_id' => $account->id, 'account_label' => $account->label()], $account, $data['pin'], $data['code'] ?? null);

            if ($approval->status === Approval::AWAITING) {
                return back()->with('success', __('injongepay::injongepay.awaiting_second_approval'));
            }

            $cashout = $cashouts->start($approval);
        } catch (StepUpRequired $e) {
            return back()->withInput($request->except('pin', 'code'))->with('step_up', ['reasons' => $e->reasons, 'method' => $e->method])->with('error', $e->getMessage());
        } catch (InjongePayException $e) {
            return back()->withInput($request->except('pin', 'code'))->with('error', $e->getMessage());
        }

        return redirect()->route('injongepay.platform.cashouts.show', $cashout->uuid)->with('success', __('injongepay::injongepay.cashout_queued', ['ref' => $cashout->reference]));
    }
}
