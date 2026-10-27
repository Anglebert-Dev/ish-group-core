<?php

namespace App\Http\Controllers\Web\GeneralLedger;

use App\Http\Controllers\Controller;
use App\Models\GeneralLedger\GeneralLedger;
use App\Services\ChartOfAccount\ChartOfAccountService;
use App\Services\GeneralLedger\GeneralLedgerService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class GeneralLedgerController extends Controller
{
    public function __construct(
        protected GeneralLedgerService $ledgers,
        protected ChartOfAccountService $accounts,
    ) {
    }

    public function index(Request $request): View
    {
        return view('app.finance.ledger.index', [
            'ledgers' => $this->ledgers->list($request),
            'accounts' => $this->accounts->postingOptions(),
            'filters' => $request->only(['from', 'to', 'reference_type', 'status', 'account_id']),
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        return ListExportLibrary::pdf($this->ledgers->exportList($request));
    }

    public function exportExcel(Request $request): Response
    {
        return ListExportLibrary::excel($this->ledgers->exportList($request));
    }

    public function show(GeneralLedger $generalLedger): View
    {
        return view('app.finance.ledger.show', [
            'ledger' => $this->ledgers->find($generalLedger),
        ]);
    }
}
