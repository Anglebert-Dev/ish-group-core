<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Settings\BusinessSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Services\ItemService;

class SettingsController extends Controller
{
    public function __construct(protected ItemService $items, protected BusinessSettings $settings)
    {
    }

    public function edit(): View
    {
        return view('inventory::settings', ['units' => $this->items->units()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['units' => ['required', 'string', 'max:2000']]);

        $units = collect(preg_split('/\R/', (string) $request->input('units')))
            ->map(fn ($unit) => mb_substr(trim($unit), 0, 20))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($units === []) {
            return back()->withErrors(['units' => __('validation.required', ['attribute' => __('inventory::inventory.units')])]);
        }

        $this->settings->set('inventory.units', $units);

        return back()->with('success', __('inventory::inventory.settings_saved'));
    }
}
