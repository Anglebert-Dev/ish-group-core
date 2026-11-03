<?php

namespace Modules\InjongePayConnect\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePayConnect\Models\ApiCall;
use Modules\InjongePayConnect\Models\Confirmation;
use Modules\InjongePayConnect\Models\Connection;

/** What a business sees of its integrations: read only. */
class ConnectionController extends Controller
{
    public function index(Request $request): View
    {
        $invoice = trim((string) $request->query('invoice'));

        return view('injongepayconnect::business.index', [
            'connections' => Connection::query()->with('integrator')->latest('id')->get(),
            'calls' => ApiCall::query()->where('business_id', $request->user('staff')->business_id)->latest('id')->limit(30)->get(),
            'invoice' => $invoice,
            'confirmations' => Confirmation::query()->with('payment')->when($invoice !== '', fn ($q) => $q->where('invoice_no', $invoice))->latest('id')->paginate(30)->withQueryString(),
        ]);
    }
}
