<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business\Business;
use App\Models\System\System;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\InjongePay\Console\SyncMerchantsCommand;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Gtbank\BankResult;
use Modules\InjongePay\Gtbank\GtbankClient;
use Modules\InjongePay\Models\BankCall;
use Modules\InjongePay\Models\LedgerEntry;
use Modules\InjongePay\Models\Wallet;
use Modules\InjongePay\Services\MerchantService;
use Modules\InjongePay\Services\WalletImport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Merchant codes of every business. Only the operator creates or changes them.
 */
class WalletController extends Controller
{
    public function __construct(protected MerchantService $merchants)
    {
    }

    public function index(Request $request): View
    {
        $wallets = $this->filtered($request)
            ->orderBy('internal_role')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('injongepay::platform.wallets.index', [
            'wallets' => $wallets,
            'businesses' => $this->businesses(),
            'waiting' => SyncMerchantsCommand::due()->count(),
        ]);
    }

    /** The list as it is filtered, for handing a business its merchant codes. */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->orderBy('merchant_code');
        $businessId = $request->query('business') ? Business::query()->whereUuid((string) $request->query('business'))->value('id') : null;
        audit('injongepay_wallets_exported', __('injongepay::injongepay.audit_wallets_exported'), null, ['filters' => $request->only(['business', 'status', 'search'])], $businessId);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['merchant_code', 'name', 'business', 'bank_status', 'enabled', 'owner', 'tin', 'phone', 'email']);
            $query->chunk(500, function ($wallets) use ($out) {
                foreach ($wallets as $w) {
                    fputcsv($out, [$w->merchant_code, $w->name, $w->business?->name, $w->bank_status, $w->is_enabled ? 'yes' : 'no', $w->kyc_business_owner, $w->kyc_tin, $w->kyc_contact_phone, $w->kyc_contact_email]);
                }
            });
            fclose($out);
        }, 'merchant-codes-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): View
    {
        return view('injongepay::platform.wallets.import', [
            'businesses' => $this->businesses(),
            'business' => $request->query('business'),
            'max' => WalletImport::MAX_ROWS,
        ]);
    }

    public function importTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'owner', 'tin', 'phone', 'email']);
            fputcsv($out, ['Kigali Branch – Till 1', 'Jane Doe', '123456789', '+250788123456', 'till1@example.com']);
            fclose($out);
        }, 'merchant-codes-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function importStore(Request $request, WalletImport $import): RedirectResponse
    {
        $data = $request->validate([
            'business' => ['required', Rule::exists('businesses', 'uuid')],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);
        $business = Business::query()->whereUuid($data['business'])->firstOrFail();

        try {
            $read = $import->read($request->file('file')->getRealPath(), $business);
        } catch (InjongePayException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($read['errors']) {
            return back()->withInput()->with('error', trans_choice('injongepay::injongepay.import_has_errors', count($read['errors'])))->with('import_errors', $read['errors']);
        }

        $wallets = $import->create($business, $read['rows']);

        return redirect()->route('injongepay.platform.wallets.index', ['business' => $business->uuid])
            ->with('success', __('injongepay::injongepay.wallets_imported', ['count' => $wallets->count(), 'from' => $wallets->first()->merchant_code, 'to' => $wallets->last()->merchant_code, 'minutes' => max(1, (int) ceil($wallets->count() * WalletImport::SPACING / 60))]));
    }

    private function filtered(Request $request)
    {
        return Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->with('business')
            ->when($request->query('business'), fn ($q, $uuid) => $q->whereIn('business_id', Business::query()->whereUuid($uuid)->select('id')))
            ->when($request->query('status'), fn ($q, $status) => $q->where('bank_status', $status))
            ->when(trim((string) $request->query('search')), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('merchant_code', 'like', "%{$s}%")));
    }

    public function create(Request $request): View
    {
        return view('injongepay::platform.wallets.create', [
            'businesses' => $this->businesses(),
            'business' => $request->query('business'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business' => ['required', Rule::exists('businesses', 'uuid')],
        ] + $this->detailRules());

        $business = Business::query()->whereUuid($data['business'])->firstOrFail();
        $wallet = $this->merchants->create($business, $data);

        return redirect()->route('injongepay.platform.wallets.show', $wallet->uuid)->with('success', __('injongepay::injongepay.wallet_created', ['code' => $wallet->merchant_code]));
    }

    public function show(string $wallet): View
    {
        $wallet = $this->find($wallet);

        return view('injongepay::platform.wallets.show', [
            'wallet' => $wallet,
            'details' => $this->merchants->details($wallet),
            'calls' => BankCall::query()->where('subject_type', $wallet->getMorphClass())->where('subject_id', $wallet->id)->latest('id')->limit(15)->get(),
            'entries' => LedgerEntry::query()->withoutGlobalScopes()->where('wallet_id', $wallet->id)->latest('id')->limit(15)->get(),
            'bankConfigured' => GtbankClient::isConfigured(),
        ]);
    }

    public function register(string $wallet): RedirectResponse
    {
        return $this->result($this->merchants->register($this->find($wallet)), 'wallet_registration_sent');
    }

    public function sync(string $wallet): RedirectResponse
    {
        return $this->result($this->merchants->sync($this->find($wallet)), 'wallet_synced');
    }

    public function syncWaiting(): RedirectResponse
    {
        $count = 0;
        SyncMerchantsCommand::eachUntilNoAnswer(SyncMerchantsCommand::due()->get(), function (Wallet $wallet) use (&$count) {
            $count++;

            return $this->merchants->sync($wallet);
        });

        return back()->with('success', __('injongepay::injongepay.wallets_synced', ['count' => $count]));
    }

    public function update(Request $request, string $wallet): RedirectResponse
    {
        $wallet = $this->find($wallet);

        try {
            $this->merchants->updateDetails($wallet, $request->validate($this->detailRules()));
        } catch (InjongePayException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.wallet_details_saved'));
    }

    public function toggle(Request $request, string $wallet): RedirectResponse
    {
        $wallet = $this->find($wallet);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->merchants->setEnabled($wallet, ! $wallet->is_enabled, $data['reason']);

        return back()->with('success', __($wallet->is_enabled ? 'injongepay::injongepay.wallet_enabled' : 'injongepay::injongepay.wallet_disabled'));
    }

    public function close(Request $request, string $wallet): RedirectResponse
    {
        $wallet = $this->find($wallet);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $this->merchants->close($wallet, $data['reason']);
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.wallet_closed'));
    }

    private function result(BankResult $result, string $okKey): RedirectResponse
    {
        return $result->ok()
            ? back()->with('success', __('injongepay::injongepay.'.$okKey))
            : back()->with('error', __('injongepay::injongepay.bank_said', ['outcome' => __('injongepay::injongepay.bank_outcome_'.$result->outcome), 'message' => $result->message()]));
    }

    private function find(string $uuid): Wallet
    {
        return Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereUuid($uuid)->firstOrFail();
    }

    /** @return array<string, string> uuid => name */
    private function businesses(): array
    {
        return Business::query()->whereKeyNot((int) System::getProperty('primary_business_id'))->orderBy('name')->pluck('name', 'uuid')->all();
    }

    private function detailRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'kyc_business_owner' => ['nullable', 'string', 'max:255'],
            'kyc_tin' => ['nullable', 'digits:9'],
            'kyc_contact_phone' => ['nullable', 'regex:/^\+250\d{9}$/'],
            'kyc_contact_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
