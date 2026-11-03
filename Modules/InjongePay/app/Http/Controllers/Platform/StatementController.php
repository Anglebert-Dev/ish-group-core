<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\StatementCheck;
use Modules\InjongePay\Services\StatementCheck as Checker;

class StatementController extends Controller
{
    public function index(): View
    {
        return view('injongepay::platform.statements.index', [
            'checks' => StatementCheck::query()->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request, Checker $checker): RedirectResponse
    {
        $data = $request->validate([
            'statement' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
        ]);

        try {
            $check = $checker->run($request->file('statement')->getRealPath(), $request->file('statement')->getClientOriginalName(), Carbon::parse($data['from']), Carbon::parse($data['to']));
        } catch (InjongePayException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('injongepay.platform.statements.show', $check)->with($check->agrees ? 'success' : 'error', __($check->agrees ? 'injongepay::injongepay.statement_agrees' : 'injongepay::injongepay.statement_differs'));
    }

    public function show(StatementCheck $check): View
    {
        return view('injongepay::platform.statements.show', ['check' => $check]);
    }
}
