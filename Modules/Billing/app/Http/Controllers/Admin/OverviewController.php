<?php

namespace Modules\Billing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Support\Money;

class OverviewController extends Controller
{
    public function index(): View
    {
        $paying = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->get();

        // Monthly recurring revenue: every paying subscription normalised to 30.4 days.
        $mrr = $paying->sum(fn (Subscription $s) => Money::toCents($s->price())
            / max(1, $s->interval()->days() * $s->intervalCount()) * (365 / 12));

        $statusCounts = Subscription::query()
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $canceledLast30 = Subscription::query()->where('status', SubscriptionStatus::Canceled->value)
            ->where('ended_at', '>=', now()->subDays(30))->count();
        $activeStart = max(1, $paying->count() + $canceledLast30);

        return view('billing::admin.overview', [
            'mrr' => Money::fromCents((int) round($mrr)),
            'statusCounts' => $statusCounts,
            'churn' => round($canceledLast30 / $activeStart * 100, 1),
            'revenueThisMonth' => Payment::query()->where('status', PaymentStatus::Approved->value)
                ->where('reviewed_at', '>=', now()->startOfMonth())->sum('amount'),
            'outstanding' => Invoice::query()->where('status', InvoiceStatus::Open->value)
                ->get()->sum(fn (Invoice $i) => $i->balanceCents()) / 100,
            'pendingPayments' => Payment::query()->with(['business', 'invoice'])
                ->where('status', PaymentStatus::Pending->value)->latest('id')->limit(8)->get(),
            'recentPayments' => Payment::query()->with(['business', 'invoice'])
                ->where('status', PaymentStatus::Approved->value)->latest('reviewed_at')->limit(8)->get(),
        ]);
    }
}
