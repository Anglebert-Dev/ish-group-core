<?php

namespace App\Http\Controllers\Web\ChartOfAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChartOfAccount\StoreChartOfAccountRequest;
use App\Http\Requests\ChartOfAccount\UpdateChartOfAccountRequest;
use App\Models\ChartOfAccount\ChartOfAccount;
use App\Services\ChartOfAccount\ChartOfAccountService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\ChartOfAccount\UpdateChartOfAccountStatusRequest;
use App\Enums\ChartOfAccountStatus;

class ChartOfAccountController extends Controller
{
    public function __construct(protected ChartOfAccountService $accounts)
    {
    }

    public function index(): View
    {
        return view('app.finance.chart-of-accounts.index', [
            'accounts' => $this->accounts->list(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->accounts->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->accounts->exportList());
    }

    public function create(): View
    {
        return view('app.finance.chart-of-accounts.create', [
            'parents' => $this->accounts->allOrdered(),
        ]);
    }

    public function store(StoreChartOfAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_posting'] = $request->boolean('is_posting', true);
        $result = $this->accounts->create($data);

        if (! ($result['status'] ?? false)) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('finance.chart-of-accounts.index')->with('success', $result['message']);
    }

    public function show(ChartOfAccount $chartOfAccount): View
    {
        return view('app.finance.chart-of-accounts.show', [
            'account' => $chartOfAccount->load('parent'),
        ]);
    }

    public function edit(ChartOfAccount $chartOfAccount): View
    {
        return view('app.finance.chart-of-accounts.edit', [
            'account' => $chartOfAccount,
            'parents' => $this->accounts->allOrdered()->where('id', '!=', $chartOfAccount->id),
        ]);
    }

    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $data = $request->validated();
        $data['is_posting'] = $request->boolean('is_posting', $chartOfAccount->is_posting);
        $result = $this->accounts->update($chartOfAccount, $data);

        if (! ($result['status'] ?? false)) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('finance.chart-of-accounts.index')->with('success', $result['message']);
    }


    public function updateStatus(UpdateChartOfAccountStatusRequest $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $status = ChartOfAccountStatus::from($request->validated('status'));
        $result = $this->accounts->updateStatus($chartOfAccount, $status);

        return redirect()
            ->route('finance.chart-of-accounts.show', $chartOfAccount)
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $result = $this->accounts->delete($chartOfAccount);

        return redirect()->route('finance.chart-of-accounts.index')
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
