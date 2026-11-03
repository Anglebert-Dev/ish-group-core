<?php

namespace Modules\InjongePayConnect\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business\Business;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePayConnect\Models\ApiCall;

class CallController extends Controller
{
    public function index(Request $request): View
    {
        return view('injongepayconnect::platform.calls.index', [
            'calls' => ApiCall::query()
                ->when($request->query('business'), fn ($q, $uuid) => $q->whereIn('business_id', Business::query()->whereUuid($uuid)->select('id')))
                ->when($request->query('result'), fn ($q, $r) => $r === 'errors' ? $q->where('status', '>=', 400) : $q->where('status', '<', 400))
                ->when(trim((string) $request->query('path')), fn ($q, $p) => $q->where('path', 'like', "%{$p}%"))
                ->latest('id')->paginate(50)->withQueryString(),
            'businesses' => Business::query()->orderBy('name')->pluck('name', 'uuid')->all(),
            'names' => Business::query()->pluck('name', 'id')->all(),
        ]);
    }

    public function show(ApiCall $call): View
    {
        return view('injongepayconnect::platform.calls.show', [
            'call' => $call,
            'connection' => $call->connection_id ? \Modules\InjongePayConnect\Models\Connection::query()->withoutGlobalScopes()->with(['integrator', 'business'])->find($call->connection_id) : null,
        ]);
    }
}
