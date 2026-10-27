<?php

namespace App\Http\Controllers\Web\Expense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Models\Expense\Expense;
use App\Models\Supplier\Supplier;
use App\Services\ChartOfAccount\ChartOfAccountService;
use App\Services\Expense\ExpenseService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenses,
        protected ChartOfAccountService $accounts,
    ) {
    }

    public function index(): View
    {
        return view('app.finance.expenses.index', [
            'expenses' => $this->expenses->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->expenses->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->expenses->exportList());
    }

    public function create(): View
    {
        return view('app.finance.expenses.create', [
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'accounts' => $this->accounts->expenseOptions(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['paid'] = $request->boolean('paid');
        $data['post_now'] = $request->boolean('post_now', true);
        $result = $this->expenses->create($data);

        if (! ($result['status'] ?? false)) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('finance.expenses.index')->with('success', $result['message']);
    }

    public function show(Expense $expense): View
    {
        return view('app.finance.expenses.show', [
            'expense' => $expense->load(['supplier', 'account']),
        ]);
    }

    public function edit(Expense $expense): View
    {
        return view('app.finance.expenses.edit', [
            'expense' => $expense,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'accounts' => $this->accounts->expenseOptions(),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $data = $request->validated();
        $data['paid'] = $request->boolean('paid');
        $result = $this->expenses->update($expense, $data);

        if (! ($result['status'] ?? false)) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('finance.expenses.index')->with('success', $result['message']);
    }

    public function post(Expense $expense): RedirectResponse
    {
        $result = $this->expenses->post($expense);

        return redirect()->route('finance.expenses.show', $expense)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $result = $this->expenses->delete($expense);

        return redirect()->route('finance.expenses.index')
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
