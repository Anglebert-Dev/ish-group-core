<?php

namespace Modules\InjongePay\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\Reports;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request, Reports $reports): View
    {
        [$from, $to] = $this->period($request);

        return view('injongepay::business.reports', [
            'from' => $from, 'to' => $to,
            'summary' => $reports->summary((int) $request->user('staff')->business_id, $from, $to),
        ]);
    }

    public function export(Request $request, Reports $reports, string $format): Response
    {
        [$from, $to] = $this->period($request);
        $export = $reports->paymentsExport((int) $request->user('staff')->business_id, $from, $to);
        audit('injongepay_report_exported', __('injongepay::injongepay.audit_exported', ['from' => $from->toDateString(), 'to' => $to->toDateString()]), null, ['format' => $format]);

        return $format === 'pdf' ? ListExportLibrary::pdf($export) : ListExportLibrary::excel($export);
    }

    public function ledger(Request $request, string $wallet): View
    {
        $wallet = Wallet::query()->whereNull('internal_role')->where('uuid', $wallet)->firstOrFail();

        return view('injongepay::business.ledger', [
            'wallet' => $wallet,
            'entries' => LedgerEntry::query()->where('wallet_id', $wallet->id)->latest('id')->paginate(50),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return [Carbon::parse($data['from'] ?? now()->startOfMonth()->toDateString()), Carbon::parse($data['to'] ?? now()->toDateString())];
    }
}
