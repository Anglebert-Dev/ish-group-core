<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\BankCall;
use Modules\InjongePay\Models\Cashout;
use Modules\InjongePay\Services\CashoutService;

/** Every business's cash-outs; the only place an unclear one is settled. */
class CashoutController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        return view('injongepay::platform.cashouts.index', [
            'cashouts' => Cashout::query()->withoutGlobalScopes()->with('wallet.business')
                ->when($status === 'open', fn ($q) => $q->whereIn('status', Cashout::OPEN))
                ->when($status && $status !== 'open', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'review' => Cashout::query()->withoutGlobalScopes()->where('status', Cashout::NEEDS_REVIEW)->count(),
        ]);
    }

    public function show(string $cashout): View
    {
        $cashout = $this->find($cashout);

        return view('injongepay::platform.cashouts.show', [
            'cashout' => $cashout->load(['wallet.business', 'events']),
            'calls' => BankCall::query()->where('reference', $cashout->reference)->orWhere(fn ($q) => $q->where('subject_type', $cashout->getMorphClass())->where('subject_id', $cashout->id))->latest('id')->get(),
        ]);
    }

    public function check(string $cashout, CashoutService $cashouts): RedirectResponse
    {
        $state = $cashouts->check($this->find($cashout), 'operator');

        return back()->with('success', __('injongepay::injongepay.cashout_checked', ['state' => __('injongepay::injongepay.answer_'.$state)]));
    }

    public function resolve(Request $request, string $cashout, CashoutService $cashouts): RedirectResponse
    {
        $data = $request->validate(['outcome' => ['required', 'in:completed,failed'], 'note' => ['required', 'string', 'min:10', 'max:1000']]);

        try {
            $cashouts->resolve($this->find($cashout), $data['outcome'], $data['note']);
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.cashout_resolved_'.$data['outcome']));
    }

    private function find(string $uuid): Cashout
    {
        return Cashout::query()->withoutGlobalScopes()->whereUuid($uuid)->firstOrFail();
    }
}
