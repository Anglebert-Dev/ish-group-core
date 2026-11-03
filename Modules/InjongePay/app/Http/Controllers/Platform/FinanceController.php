<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Libraries\ListExport;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\InjongePay\Services\InternalAccounts;
use Modules\InjongePay\Services\Reconciliation;
use Modules\InjongePay\Services\Reports;
use Modules\InjongePay\Support\Money;
use Symfony\Component\HttpFoundation\Response;

/** The platform's money: every business, its own fee wallets, and whether it all adds up. */
class FinanceController extends Controller
{
    public function index(Request $request, Reports $reports, InternalAccounts $internal, Reconciliation $reconciliation): View
    {
        [$from, $to] = $this->period($request);

        return view('injongepay::platform.finance', [
            'from' => $from, 'to' => $to,
            'summary' => $reports->summary(null, $from, $to),
            'rows' => $reports->byBusiness($from, $to),
            'bankCut' => rescue(fn () => $internal->bankCut(), null, false),
            'revenue' => rescue(fn () => $internal->platformRevenue(), null, false),
            'difference' => $reconciliation->platformDifference(),
        ]);
    }

    public function export(Request $request, Reports $reports): Response
    {
        [$from, $to] = $this->period($request);
        $rows = $reports->byBusiness($from, $to)->map(fn ($r) => [
            $r->name, (int) $r->payments, Money::toDecimal((int) $r->gross), Money::toDecimal((int) $r->fees), Money::toDecimal((int) $r->paid),
            Money::toDecimal((int) $r->available), Money::toDecimal((int) $r->pending),
        ])->all();
        audit('injongepay_report_exported', __('injongepay::injongepay.audit_exported', ['from' => $from->toDateString(), 'to' => $to->toDateString()]), null, ['scope' => 'platform']);

        return ListExportLibrary::excel(ListExport::make(__('injongepay::injongepay.finance').' '.$from->toDateString().' – '.$to->toDateString(), 'injonge-pay-finance', [
            __('body.business'), __('injongepay::injongepay.payments'), __('injongepay::injongepay.gross'), __('injongepay::injongepay.fee'),
            __('injongepay::injongepay.cashouts_paid'), __('injongepay::injongepay.available'), __('injongepay::injongepay.pending'),
        ], $rows));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return [Carbon::parse($data['from'] ?? now()->startOfMonth()->toDateString()), Carbon::parse($data['to'] ?? now()->toDateString())];
    }
}
