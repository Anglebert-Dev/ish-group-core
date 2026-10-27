<?php

namespace Modules\Billing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Libraries\ListExportLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\PaymentService;
use Modules\Billing\Support\BillingExports;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $payments)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');

        return view('billing::admin.payments.index', [
            'payments' => $this->filtered($status)
                ->with(['business', 'invoice', 'submitter', 'reviewer'])
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $export = BillingExports::payments($this->filtered($request->query('status', 'all')));

        return $format === 'pdf' ? ListExportLibrary::pdf($export) : ListExportLibrary::excel($export);
    }

    private function filtered(string $status): Builder
    {
        return Payment::query()->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest('id');
    }

    public function proof(Payment $payment): StreamedResponse
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path);
    }

    public function approve(Payment $payment): RedirectResponse
    {
        $result = $this->payments->approve($payment, Auth::guard('staff')->user());

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $result = $this->payments->reject($payment, Auth::guard('staff')->user(), $data['reason']);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
