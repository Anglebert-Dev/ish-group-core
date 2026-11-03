<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\InjongePay\Models\FeeRateChange;
use Modules\InjongePay\Services\FeeSettings;
use Modules\InjongePay\Support\PlatformSettings;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('injongepay::platform.settings', [
            'fee' => PlatformSettings::feePercentage(),
            'bankCut' => PlatformSettings::bankCutPercentage(),
            'hasSecret' => PlatformSettings::webhookSecret() !== null,
            'allowedIps' => implode(', ', PlatformSettings::webhookAllowedIps()),
            'newSecret' => session('injongepay_new_secret'),
            'webhookUrls' => [
                __('injongepay::injongepay.webhook_payments') => url('/api/payments/webhook'),
                __('injongepay::injongepay.webhook_disbursements') => url('/api/gtbank/disbursement-status'),
            ],
            'bankConfigured' => filled(config('injongepay.gtbank.base_url')),
            'history' => FeeRateChange::query()->whereNull('business_id')->latest('id')->limit(30)->get(),
        ]);
    }

    public function updateFees(Request $request, FeeSettings $fees): RedirectResponse
    {
        $data = $request->validate([
            'fee_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'bank_cut_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ]);

        $fees->update((string) $data['fee_percentage'], (string) $data['bank_cut_percentage']);

        return back()->with('success', __('injongepay::injongepay.fees_saved'));
    }

    public function updateIps(Request $request): RedirectResponse
    {
        $data = $request->validate(['webhook_allowed_ips' => ['nullable', 'string', 'max:500', function ($attribute, $value, $fail) {
            foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $ip) {
                [$address, $mask] = array_pad(explode('/', $ip, 2), 2, null);
                if (! filter_var($address, FILTER_VALIDATE_IP) || ($mask !== null && (! ctype_digit($mask) || (int) $mask > 128))) {
                    $fail(__('injongepay::injongepay.err_ip', ['ip' => $ip]));
                }
            }
        }]]);

        $old = implode(', ', PlatformSettings::webhookAllowedIps());
        PlatformSettings::set('webhook_allowed_ips', implode(',', array_filter(array_map('trim', explode(',', (string) ($data['webhook_allowed_ips'] ?? ''))))));
        audit('injongepay_webhook_ips_changed', __('injongepay::injongepay.audit_webhook_ips'), null, ['old' => $old, 'new' => implode(', ', PlatformSettings::webhookAllowedIps())]);

        return back()->with('success', __('injongepay::injongepay.ips_saved'));
    }

    /** A new secret is shown once; there is no field to type one (it must be random). */
    public function generateSecret(): RedirectResponse
    {
        $secret = Str::random(40);
        PlatformSettings::setWebhookSecret($secret);
        audit('injongepay_webhook_secret_generated', __('injongepay::injongepay.audit_webhook_secret'));

        return back()->with('injongepay_new_secret', $secret)->with('success', __('injongepay::injongepay.secret_generated'));
    }
}
