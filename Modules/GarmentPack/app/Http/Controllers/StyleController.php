<?php

namespace Modules\GarmentPack\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\GarmentPack\Models\Style;
use Modules\GarmentPack\Services\StyleService;
use Modules\Inventory\Enums\ItemType;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Variant;
use Modules\Inventory\Services\ItemService;
use Modules\Production\Models\WorkOrder;

class StyleController extends Controller
{
    public function __construct(protected StyleService $styles, protected ItemService $items)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('garmentpack::styles.index', [
            'styles' => Style::query()->with('item')
                ->when($q, fn ($query) => $query->where(fn ($w) => $w->whereRaw('LOWER(code) LIKE ?', ['%'.mb_strtolower($q).'%'])->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($q).'%'])))
                ->orderBy('code')->paginate(20)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Style(['is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $style = new Style;
        $result = $this->styles->save($style, ...$this->validated($request, $style));

        return redirect()->route('garmentpack.styles.show', $result['data'])->with('success', $result['message']);
    }

    public function show(Style $style): View
    {
        $style->load(['item.variants', 'materials.variant.item']);

        return view('garmentpack::styles.show', ['style' => $style, 'costing' => $this->styles->costing($style)]);
    }

    public function edit(Style $style): View
    {
        return $this->form($style->load('materials.variant'));
    }

    public function update(Request $request, Style $style): RedirectResponse
    {
        $result = $this->styles->save($style, ...$this->validated($request, $style));

        return redirect()->route('garmentpack.styles.show', $style)->with('success', $result['message']);
    }

    public function issue(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $request->validate(['store' => ['required', 'string']]);
        $style = Style::query()->where('item_id', $workOrder->variant?->item_id)->firstOrFail();
        $store = Store::query()->where('uuid', $request->input('store'))->where('is_active', true)->firstOrFail();
        $result = $this->styles->issueRemaining($style, $workOrder, $store);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    /**
     * @return array{0: array, 1: list<array>}
     */
    private function validated(Request $request, Style $style): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('garment_styles', 'code')->where('business_id', auth('staff')->user()->business_id)->ignore($style->id)],
            'name' => ['required', 'string', 'max:255'],
            'item' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:5000'],
            'labour_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'materials' => ['nullable', 'array', 'max:100'],
            'materials.*.variant' => ['nullable', 'string'],
            'materials.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'materials.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'materials.*.note' => ['nullable', 'string', 'max:255'],
            'piece_rates' => ['nullable', 'array'],
            'piece_rates.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $item = Item::query()->where('uuid', $data['item'])->where('type', ItemType::Finished->value)->firstOrFail();

        if (Style::query()->where('item_id', $item->id)->where('id', '!=', $style->id ?? 0)->exists()) {
            throw ValidationException::withMessages(['item' => __('garmentpack::garment.item_taken')]);
        }

        $materials = [];
        foreach ((array) ($data['materials'] ?? []) as $row) {
            if (blank($row['variant'] ?? null) || (float) ($row['quantity'] ?? 0) <= 0) {
                continue;
            }
            $variant = Variant::query()->where('uuid', $row['variant'])->first();
            if ($variant) {
                $materials[] = ['variant' => $variant, 'quantity' => (float) $row['quantity'], 'waste_percent' => (float) ($row['waste_percent'] ?? 0), 'note' => $row['note'] ?? null];
            }
        }

        return [[
            'code' => $data['code'],
            'name' => $data['name'],
            'item_id' => $item->id,
            'description' => $data['description'] ?? null,
            'labour_cost' => (float) ($data['labour_cost'] ?? 0),
            'overhead_cost' => (float) ($data['overhead_cost'] ?? 0),
            'piece_rates' => array_map('floatval', array_filter((array) ($data['piece_rates'] ?? []), fn ($v) => $v !== null && $v !== '')),
            'is_active' => $request->boolean('is_active', true),
        ], $materials];
    }

    private function form(Style $style): View
    {
        return view('garmentpack::styles.form', [
            'style' => $style,
            'products' => Item::query()->where('type', ItemType::Finished->value)->where('is_active', true)->orderBy('name')->pluck('name', 'uuid')->all(),
            'materialOptions' => $this->items->lineOptions(ItemType::Material, true),
            'stages' => app(\Modules\Production\Services\WorkOrderService::class)->stages(),
        ]);
    }
}
