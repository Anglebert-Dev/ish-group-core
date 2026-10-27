<?php

namespace App\Http\Controllers\Web\AccountAssignment;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountAssignment\UpdateAccountAssignmentRequest;
use App\Models\AccountAssignment\AccountAssignment;
use App\Services\AccountAssignment\AccountAssignmentService;
use App\Services\ChartOfAccount\ChartOfAccountService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AccountAssignmentController extends Controller
{
    public function __construct(
        protected AccountAssignmentService $assignments,
        protected ChartOfAccountService $accounts,
    ) {
    }

    public function index(): View
    {
        return view('app.finance.account-assignments.index', [
            'assignments' => $this->assignments->list(),
            'accounts' => $this->accounts->postingOptions(),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->assignments->exportList());
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->assignments->exportList());
    }

    public function update(UpdateAccountAssignmentRequest $request, AccountAssignment $accountAssignment): RedirectResponse
    {
        $result = $this->assignments->update($accountAssignment, $request->validated());

        return redirect()->route('finance.account-assignments.index')->with('success', $result['message']);
    }
}
