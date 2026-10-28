<?php

namespace Modules\Quotations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Services\ItemService;
use Modules\Inventory\Services\StoreService;
use Modules\Inventory\Support\DocumentLines;
use Modules\Quotations\Enums\QuoteStatus;
use Modules\Quotations\Enums\SampleStatus;
use Modules\Quotations\Models\Quotation;
use Modules\Quotations\Models\QuotationDeposit;
use Modules\Quotations\Services\QuotationService;

class QuotationController extends Controller
{
    public function __construct(
        protected QuotationService $quotes,
        protected ItemService $items,
        protected StoreService $stores,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(QuotationService::FILTERS);

        return view('quotations::quotes.index', ['quotes' => $this->quotes->list($filters), 'filters' => $filters]);
    }

    public function create(Request $request): View
    {
        $quote = new Quotation([
            'quote_date' => now(),
            'valid_until' => now()->addDays((int) config('quotations.valid_days', 30)),
            'follow_up_date' => now()->addDays(3),
            'is_sample' => $request->boolean('sample'),
        ]);
        $quote->setRelation('lines', collect());

        return $this->form($quote);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);
        $result = $this->quotes->create($data + ['is_sample' => $request->boolean('is_sample')], $lines);

        return redirect()->route('quotations.quotes.show', $result['data'])->with('success', $result['message']);
    }

    public function show(Quotation $quote): View
    {
        return view('quotations::quotes.show', [
            'quote' => $quote->load(['customer', 'store', 'lines.variant.item', 'deposits', 'salesOrder']),
        ]);
    }

    public function edit(Quotation $quote): View|RedirectResponse
    {
        if ($quote->status !== QuoteStatus::Open) {
            return redirect()->route('quotations.quotes.show', $quote)->with('error', __('quotations::quotations.not_open'));
        }

        return $this->form($quote->load('lines.variant'));
    }

    public function update(Request $request, Quotation $quote): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);
        $result = $this->quotes->update($quote, $data, $lines);

        return redirect()->route('quotations.quotes.show', $quote)->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function status(Request $request, Quotation $quote): RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::in([QuoteStatus::Open->value, QuoteStatus::Accepted->value, QuoteStatus::Rejected->value])]]);

        return $this->respond($this->quotes->setStatus($quote, QuoteStatus::from($request->input('status'))));
    }

    public function sample(Request $request, Quotation $quote): RedirectResponse
    {
        $request->validate(['sample_status' => ['required', Rule::enum(SampleStatus::class)]]);

        return $this->respond($this->quotes->setSampleStatus($quote, SampleStatus::from($request->input('sample_status'))));
    }

    public function convert(Quotation $quote): RedirectResponse
    {
        $result = $this->quotes->convert($quote);

        return $result['status']
            ? redirect()->route('salesorders.orders.show', $result['data'])->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }

    public function deposit(Request $request, Quotation $quote): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'string', Rule::in(['cash', 'mobile_money', 'bank', 'cheque', 'card', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->respond($this->quotes->addDeposit($quote, $data));
    }

    public function reverseDeposit(Request $request, Quotation $quote, QuotationDeposit $deposit): RedirectResponse
    {
        abort_unless($deposit->quotation_id === $quote->id, 404);
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->respond($this->quotes->reverseDeposit($deposit, $request->input('reason')));
    }

    private function respond(array $result): RedirectResponse
    {
        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    /**
     * @return array{0: array, 1: list<array>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer' => ['required', 'string'],
            'store' => ['required', 'string'],
            'quote_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quote_date'],
            'follow_up_date' => ['nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ] + DocumentLines::rules());

        $customer = Customer::query()->where('uuid', $data['customer'])->firstOrFail();
        $store = Store::query()->where('uuid', $data['store'])->where('is_active', true)->firstOrFail();

        return [[
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'quote_date' => $data['quote_date'],
            'valid_until' => $data['valid_until'] ?? null,
            'follow_up_date' => $data['follow_up_date'] ?? null,
            'discount' => (float) ($data['discount'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ], DocumentLines::resolve((array) $request->input('lines', []))];
    }

    private function form(Quotation $quote): View
    {
        return view('quotations::quotes.form', [
            'quote' => $quote,
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get()
                ->mapWithKeys(fn (Customer $c) => [$c->uuid => $c->displayName().($c->phone ? ' · '.$c->phone : '')])->all(),
            'stores' => $this->stores->all(true)->mapWithKeys(fn ($s) => [$s->uuid => $s->name])->all(),
            'defaultStore' => $this->stores->default(auth('staff')->user()->business)->uuid,
            'lineOptions' => $this->items->lineOptions(),
        ]);
    }
}
