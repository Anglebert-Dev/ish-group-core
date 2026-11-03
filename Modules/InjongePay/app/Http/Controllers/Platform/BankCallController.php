<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePay\Models\BankCall;

class BankCallController extends Controller
{
    public function index(Request $request): View
    {
        $calls = BankCall::query()
            ->when($request->query('endpoint'), fn ($q, $e) => $q->where('endpoint', $e))
            ->when($request->query('outcome'), fn ($q, $o) => $q->where('outcome', $o))
            ->when($request->query('direction'), fn ($q, $d) => $q->where('direction', $d))
            ->when(trim((string) $request->query('reference')), fn ($q, $r) => $q->where('reference', 'like', "%{$r}%"))
            ->latest('id')->paginate(50)->withQueryString();

        return view('injongepay::platform.bank-calls.index', [
            'calls' => $calls,
            'endpoints' => BankCall::query()->distinct()->orderBy('endpoint')->pluck('endpoint', 'endpoint')->all(),
        ]);
    }

    public function show(BankCall $call): View
    {
        return view('injongepay::platform.bank-calls.show', ['call' => $call]);
    }
}
