<?php

namespace Modules\Billing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Billing\Models\Package;
use Modules\Billing\Support\BillingSettings;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('billing::admin.settings', [
            'settings' => BillingSettings::all(),
            'packages' => Package::query()->where('is_active', true)->ordered()->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_inclusive' => ['boolean'],
            'tax_label' => ['nullable', 'string', 'max:30'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:90'],
            'reminder_days' => ['required', 'integer', 'min:0', 'max:60'],
            'due_days' => ['required', 'integer', 'min:0', 'max:90'],
            'invoice_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'default_package_id' => ['nullable', 'integer', 'exists:billing_packages,id'],
            'payment_instructions' => ['nullable', 'string', 'max:3000'],
            'issuer_name' => ['nullable', 'string', 'max:255'],
            'issuer_address' => ['nullable', 'string', 'max:500'],
            'issuer_tax_id' => ['nullable', 'string', 'max:60'],
            'issuer_email' => ['nullable', 'email', 'max:255'],
            'issuer_phone' => ['nullable', 'string', 'max:60'],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['invoice_prefix'] = strtoupper($data['invoice_prefix']);
        $data['tax_inclusive'] = $request->boolean('tax_inclusive') ? '1' : '0';

        BillingSettings::set($data);

        return redirect()->route('superadmin.billing.settings.index')->with('success', __('body.settings_updated'));
    }
}
