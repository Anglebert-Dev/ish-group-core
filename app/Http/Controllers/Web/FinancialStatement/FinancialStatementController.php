<?php

namespace App\Http\Controllers\Web\FinancialStatement;

use App\Http\Controllers\Controller;
use App\Services\FinancialStatement\FinancialStatementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialStatementController extends Controller
{
    public function __construct(protected FinancialStatementService $statements)
    {
    }

    public function index(): View
    {
        return view('app.finance.statements.index');
    }

    public function trialBalance(Request $request): View
    {
        $data = $this->statements->trialBalance($request->input('from'), $request->input('to'));

        return view('app.finance.statements.trial-balance', $data);
    }

    public function profitLoss(Request $request): View
    {
        $data = $this->statements->profitAndLoss($request->input('from'), $request->input('to'));

        return view('app.finance.statements.profit-loss', $data);
    }

    public function balanceSheet(Request $request): View
    {
        $data = $this->statements->balanceSheet($request->input('as_of'));

        return view('app.finance.statements.balance-sheet', $data);
    }
}
