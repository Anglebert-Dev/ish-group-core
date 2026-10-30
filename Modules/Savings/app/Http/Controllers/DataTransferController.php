<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Savings\Http\Controllers\Concerns\UsesLedger;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Category;
use Modules\Savings\Models\ImportBatch;
use Modules\Savings\Services\DataTransfer;
use Symfony\Component\HttpFoundation\Response;

class DataTransferController extends Controller
{
    use UsesLedger;

    public function __construct(protected DataTransfer $data)
    {
    }

    public function index(): View
    {
        return view('savings::data.index', [
            'accounts' => Account::query()->where('status', 'active')->orderBy('name')->get(),
            'batches' => ImportBatch::query()->latest('id')->limit(10)->get(),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate(['account' => ['required', 'string'], 'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);
        $account = Account::query()->where('uuid', $request->input('account'))->firstOrFail();
        $batch = $this->data->preview($account, $request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName());

        return redirect()->route('savings.data.batch', $batch);
    }

    public function showBatch(ImportBatch $batch): View
    {
        return view('savings::data.batch', [
            'batch' => $batch,
            'account' => Account::query()->find($batch->account_id),
            'income' => Category::query()->where('kind', 'income')->where('is_archived', false)->orderBy('name')->get(),
            'expense' => Category::query()->where('kind', 'expense')->where('is_archived', false)->orderBy('name')->get(),
        ]);
    }

    public function commit(Request $request, ImportBatch $batch): RedirectResponse
    {
        $request->validate(['income_category' => ['required', 'string'], 'expense_category' => ['required', 'string'], 'also' => ['nullable', 'array']]);

        return $this->attempt(fn () => $this->data->commit(
            $batch,
            Category::query()->where('uuid', $request->input('income_category'))->where('kind', 'income')->firstOrFail(),
            Category::query()->where('uuid', $request->input('expense_category'))->where('kind', 'expense')->firstOrFail(),
            (array) $request->input('also', []),
        ), null, fn ($b) => redirect()->route('savings.data.batch', $b)->with('success', __('savings::savings.import_result', ['count' => $b->imported])));
    }

    public function backup(Request $request): Response
    {
        $json = json_encode($this->data->export($request->user('staff')->business_id, $request->user('staff')->id), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="savings-backup-'.now()->format('Y-m-d').'.json"',
        ]);
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:51200'], 'mode' => ['required', 'in:merge,replace']]);
        $data = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($data)) {
            return back()->with('error', __('savings::savings.backup_not_ours'));
        }

        return $this->attempt(fn () => $this->data->restore($request->user('staff')->business_id, $request->user('staff')->id, $data, $request->input('mode')), null,
            fn ($result) => back()->with('success', __('savings::savings.restored', ['count' => array_sum($result['added'])])));
    }
}
