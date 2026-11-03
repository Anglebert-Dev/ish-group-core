<?php

namespace Modules\InjongePayConnect\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\InjongePayConnect\Models\Confirmation;

/** Which invoices the business's systems linked to which payments. */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $invoice = trim((string) $request->query('invoice'));

        return view('injongepayconnect::business.invoices', [
            'invoice' => $invoice,
            'confirmations' => Confirmation::query()->with('payment')
                ->when($invoice !== '', fn ($q) => $q->where('invoice_no', 'like', "%{$invoice}%"))
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->latest('id')->paginate(30)->withQueryString(),
        ]);
    }
}
