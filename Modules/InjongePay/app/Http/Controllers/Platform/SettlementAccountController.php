<?php

namespace Modules\InjongePay\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business\Business;
use App\Models\System\System;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\SettlementAccount;
use Modules\InjongePay\Services\SettlementAccounts;

class SettlementAccountController extends Controller
{
    public function __construct(protected SettlementAccounts $accounts)
    {
    }

    public function index(Request $request): View
    {
        return view('injongepay::platform.settlement-accounts.index', [
            'accounts' => SettlementAccount::query()->withoutGlobalScopes()->whereNull('deleted_at')
                ->when($request->query('business'), fn ($q, $uuid) => $q->whereIn('business_id', Business::query()->where('uuid', $uuid)->select('id')))
                ->latest('id')->paginate(30)->withQueryString(),
            'businesses' => Business::query()->whereKeyNot((int) System::getProperty('primary_business_id'))->orderBy('name')->pluck('name', 'uuid')->all(),
            'names' => Business::query()->pluck('name', 'id')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business' => ['required', Rule::exists('businesses', 'uuid')],
            'kind' => ['required', 'in:bank,wallet'],
            'account' => ['required', 'string', 'max:20', 'regex:/^[0-9+ ]+$/'],
            'holder_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $this->accounts->add(Business::query()->where('uuid', $data['business'])->firstOrFail(), $data);
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.account_added'));
    }

    public function verify(string $account): RedirectResponse
    {
        try {
            $this->accounts->verify($this->find($account));
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.account_verified_msg'));
    }

    public function approve(Request $request, string $account): RedirectResponse
    {
        try {
            $this->accounts->approve($this->find($account), $request->user('staff'));
        } catch (InjongePayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('injongepay::injongepay.account_approved_msg', ['hours' => SettlementAccounts::COOLING_OFF_HOURS]));
    }

    public function disable(Request $request, string $account): RedirectResponse
    {
        $this->accounts->disable($this->find($account), $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']);

        return back()->with('success', __('injongepay::injongepay.account_disabled_msg'));
    }

    private function find(string $uuid): SettlementAccount
    {
        return SettlementAccount::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('uuid', $uuid)->firstOrFail();
    }
}
