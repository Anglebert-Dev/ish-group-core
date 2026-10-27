<?php

namespace App\Http\Controllers\Web\Finance;

use App\Http\Controllers\Controller;
use App\Services\GeneralLedger\GeneralLedgerService;
use Illuminate\View\View;

class FinanceHomeController extends Controller
{
    public function __construct(protected GeneralLedgerService $ledgers)
    {
    }

    public function index(): View
    {
        return view('app.finance.index', [
            'totals' => $this->ledgers->periodTotals(),
        ]);
    }
}
