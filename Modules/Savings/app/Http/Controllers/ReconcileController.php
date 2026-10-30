<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Savings\Http\Controllers\Concerns\UsesLedger;
use Modules\Savings\Models\Account;
use Modules\Savings\Models\Reconciliation;
use Modules\Savings\Services\Reconciler;

class ReconcileController extends Controller
{
    use UsesLedger;

    public function __construct(protected Reconciler $reconciler)
    {
    }

    public function index(): View
    {
        return view('savings::reconcile.index', [
            'accounts' => Account::query()->where('status', 'active')->orderBy('name')->get(),
            'history' => Reconciliation::query()->with('account')->latest('statement_date')->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['account' => ['required', 'string'], 'statement_date' => ['required', 'date', 'before_or_equal:today'], 'statement_balance' => ['required', 'string']]);

        return $this->attempt(function () use ($data) {
            $account = Account::query()->where('uuid', $data['account'])->firstOrFail();

            return $this->reconciler->check($account, $data['statement_date'], $this->money($data['statement_balance'], $account->currency, true));
        }, __('savings::savings.expected_saved'), fn ($rec) => redirect()->route('savings.reconcile.show', $rec));
    }

    public function show(Reconciliation $reconciliation): View
    {
        return view('savings::reconcile.show', ['rec' => $reconciliation->load('account'), 'look' => $this->reconciler->suggestions($reconciliation)]);
    }

    public function explain(Request $request, Reconciliation $reconciliation): RedirectResponse
    {
        $request->validate(['explanation' => ['required', 'string', 'max:2000']]);

        return $this->attempt(fn () => $this->reconciler->explain($reconciliation, $request->input('explanation')), __('savings::savings.expected_saved'));
    }

    public function adjust(Request $request, Reconciliation $reconciliation): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->reconciler->adjust($reconciliation, $request->input('reason')));
    }
}
