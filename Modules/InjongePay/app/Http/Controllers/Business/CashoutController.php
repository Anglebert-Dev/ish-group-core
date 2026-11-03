<?php

namespace Modules\InjongePay\Http\Controllers\Business;

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
use Modules\InjongePay\Support\Money;

/** A business asks for cash-outs and follows them; it never reverses or retries one. */
class CashoutController extends Controller
{
    public function index(): View
    {
        return view('injongepay::business.cashouts.index', [
            'cashouts' => Cashout::query()->with('wallet')->latest('id')->paginate(30),
            'wallets' => Wallet::query()->whereNull('internal_role')->where('is_enabled', true)->orderBy('name')->get()
                ->mapWithKeys(fn (Wallet $w) => [$w->uuid => $w->name.' · '.$w->merchant_code.' · '.Money::format($w->available_balance)])->all(),
            'accounts' => SettlementAccount::query()->where('status', SettlementAccount::APPROVED)->get()
                ->mapWithKeys(fn (SettlementAccount $a) => [$a->uuid => $a->label()])->all(),
        ]);
    }

    public function store(Request $request, Approvals $approvals, CashoutService $cashouts): RedirectResponse
    {
        $data = $request->validate([
            'wallet' => ['required', 'string'],
            'account' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'pin' => ['required', 'string'],
            'code' => ['nullable', 'string', 'max:10'],
        ], ['amount.integer' => __('injongepay::injongepay.err_whole_rwf')]);

        try {
            $wallet = Wallet::query()->whereNull('internal_role')->where('uuid', $data['wallet'])->firstOrFail();
            $account = SettlementAccount::query()->where('uuid', $data['account'])->firstOrFail();
            $amount = Money::toCents((string) $data['amount']);
            $cashouts->validate($wallet, $account, $amount);

            $approval = $approvals->request($request, $request->user('staff'), $wallet, 'cashout', $amount,
                ['settlement_account_id' => $account->id, 'account_label' => $account->label()], $account, $data['pin'], $data['code'] ?? null);

            if ($approval->status === Approval::AWAITING) {
                return redirect()->route('injongepay.cashouts.index')->with('success', __('injongepay::injongepay.awaiting_second_approval'));
            }

            $cashout = $cashouts->start($approval);
        } catch (StepUpRequired $e) {
            return back()->withInput($request->except('pin', 'code'))->with('step_up', ['reasons' => $e->reasons, 'method' => $e->method])->with('error', $e->getMessage());
        } catch (InjongePayException $e) {
            return back()->withInput($request->except('pin', 'code'))->with('error', $e->getMessage());
        }

        return redirect()->route('injongepay.cashouts.show', $cashout)->with('success', __('injongepay::injongepay.cashout_queued', ['ref' => $cashout->reference]));
    }

    public function show(Cashout $cashout): View
    {
        return view('injongepay::business.cashouts.show', ['cashout' => $cashout->load(['wallet', 'events'])]);
    }
}
