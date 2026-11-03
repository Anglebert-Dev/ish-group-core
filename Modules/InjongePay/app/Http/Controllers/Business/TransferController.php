<?php

namespace Modules\InjongePay\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Exceptions\StepUpRequired;
use Modules\InjongePay\Models\Approval;
use Modules\InjongePay\Models\Transfer;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\Approvals;
use Modules\InjongePay\Services\TransferService;
use Modules\InjongePay\Support\Money;

class TransferController extends Controller
{
    public function index(Request $request, \Modules\InjongePay\Services\Security\Pins $pins, TransferService $transfers): View
    {
        $ids = Wallet::query()->pluck('id');

        // Step 1 (as before): say whose merchant code it is before anything is asked for.
        $destination = null;
        $problem = null;
        $from = Str::isUuid((string) $request->query('wallet')) ? Wallet::query()->whereNull('internal_role')->whereUuid($request->query('wallet'))->first() : null;

        if ($from && filled($request->query('to'))) {
            try {
                $destination = $transfers->destination((string) $request->query('to'), $from);
            } catch (InjongePayException $e) {
                $problem = $e->getMessage();
            }
        }

        return view('injongepay::business.transfers.index', [
            'transfers' => Transfer::query()->withoutGlobalScopes()->with(['fromWallet', 'toWallet'])
                ->where(fn ($q) => $q->whereIn('from_wallet_id', $ids)->orWhereIn('to_wallet_id', $ids))->latest('id')->paginate(30),
            'wallets' => $this->wallets(),
            'hasPin' => $pins->has($request->user('staff')),
            'from' => $from,
            'destination' => $destination?->load('business'),
            'problem' => $problem,
        ]);
    }

    public function store(Request $request, Approvals $approvals, TransferService $transfers): RedirectResponse
    {
        $data = $request->validate([
            'wallet' => ['required', 'string'],
            'merchant_code' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'min:1', 'decimal:0,2'],
            'description' => ['nullable', 'string', 'max:255'],
            'pin' => ['required', 'string'],
            'code' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            $from = Wallet::query()->whereNull('internal_role')->whereUuid($data['wallet'])->firstOrFail();
            $to = $transfers->destination($data['merchant_code'], $from);
            $amount = Money::toCents((string) $data['amount']);

            $approval = $approvals->request($request, $request->user('staff'), $from, 'transfer', $amount,
                ['to_wallet_id' => $to->id, 'to_merchant_code' => $to->merchant_code, 'description' => $data['description'] ?? null], null, $data['pin'], $data['code'] ?? null);

            if ($approval->status === Approval::AWAITING) {
                return redirect()->route('injongepay.transfers.index')->with('success', __('injongepay::injongepay.awaiting_second_approval'));
            }

            $transfer = $transfers->execute($approval);
        } catch (StepUpRequired $e) {
            return redirect()->route('injongepay.transfers.index', ['wallet' => $data['wallet'], 'to' => $data['merchant_code'], 'amount' => $data['amount'], 'description' => $data['description'] ?? null])
                ->with('step_up', ['reasons' => $e->reasons, 'method' => $e->method])->with('error', $e->getMessage());
        } catch (InjongePayException $e) {
            return redirect()->route('injongepay.transfers.index', ['wallet' => $data['wallet'], 'to' => $data['merchant_code'], 'amount' => $data['amount'], 'description' => $data['description'] ?? null])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('injongepay.transfers.index')->with('success', __('injongepay::injongepay.transfer_done', ['amount' => Money::format($transfer->amount), 'ref' => $transfer->reference]));
    }

    private function wallets(): array
    {
        return Wallet::query()->whereNull('internal_role')->where('is_enabled', true)->orderBy('name')->get()
            ->mapWithKeys(fn (Wallet $w) => [$w->uuid => $w->name.' · '.$w->merchant_code.' · '.Money::format($w->available_balance)])->all();
    }
}
