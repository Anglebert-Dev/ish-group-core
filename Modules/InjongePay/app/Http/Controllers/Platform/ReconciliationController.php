<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\InjongePay\Console\MirrorCommand;
use Modules\InjongePay\Models\DailyClose;
use Modules\InjongePay\Models\ReconciliationItem;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\BankMirror;
use Modules\InjongePay\Services\Reconciliation;
use Modules\InjongePay\Services\ReconciliationItems;

class ReconciliationController extends Controller
{
    public function index(Request $request, Reconciliation $reconciliation): View
    {
        $day = Carbon::parse($request->query('day', now()->subDay()->toDateString()));

        return view('injongepay::platform.reconciliation.index', [
            'items' => ReconciliationItem::query()->with(['wallet', 'business'])->where('status', $request->query('status', ReconciliationItem::OPEN))->latest('id')->paginate(30)->withQueryString(),
            'status' => $request->query('status', ReconciliationItem::OPEN),
            'day' => $day,
            'closes' => DailyClose::query()->with('wallet.business')->whereDate('day', $day)->orderBy('state')->get(),
            'difference' => $reconciliation->platformDifference(),
        ]);
    }

    public function pull(Request $request, BankMirror $mirror): RedirectResponse
    {
        $hours = (int) ($request->validate(['hours' => ['nullable', 'integer', 'min:1', 'max:744']])['hours'] ?? 24);
        $summary = ['seen' => 0, 'ingested' => 0, 'mismatches' => 0, 'reversed' => 0, 'unreachable' => 0];

        foreach (MirrorCommand::wallets() as $wallet) {
            $result = $mirror->pull($wallet, now()->subHours($hours), now());
            if ($result === null) {
                $summary['unreachable']++;

                continue;
            }
            foreach (['seen', 'ingested', 'mismatches', 'reversed'] as $key) {
                $summary[$key] += $result[$key];
            }
        }

        audit('injongepay_bank_pull', __('injongepay::injongepay.audit_bank_pull', ['hours' => $hours]), null, $summary);

        return back()->with('success', __('injongepay::injongepay.pulled', $summary));
    }

    public function closeDay(Request $request, Reconciliation $reconciliation): RedirectResponse
    {
        $day = Carbon::parse($request->validate(['day' => ['required', 'date', 'before_or_equal:today']])['day']);

        foreach (MirrorCommand::wallets() as $wallet) {
            $reconciliation->closeDay($wallet, $day);
        }
        $books = $reconciliation->checkBooks();

        return redirect()->route('injongepay.platform.reconciliation.index', ['day' => $day->toDateString()])
            ->with('success', __('injongepay::injongepay.day_closed', ['day' => $day->toDateString(), 'drifts' => $books['drifts']]));
    }

    public function resolve(Request $request, ReconciliationItem $item, ReconciliationItems $items): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:1000']]);

        if ($item->status !== ReconciliationItem::OPEN) {
            return back()->with('error', __('injongepay::injongepay.err_already_resolved'));
        }

        $items->resolve($item, $data['note']);

        return back()->with('success', __('injongepay::injongepay.item_resolved'));
    }
}
