@php use Modules\InjongePay\Support\Money; use App\Helpers\AuthHelper; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="$wallet->label()">
    <x-common.page-header :title="$wallet->name" :description="$wallet->business?->name.' · '.$wallet->merchant_code">
        <x-slot:actions>
            @if (! $wallet->isInternal() && AuthHelper::can('update injongepay_platform.bank'))
                @if (in_array($wallet->bank_status, ['NOT_REGISTERED', 'FAILED', 'REGISTERING'], true))
                    <x-common.action-button :action="route('injongepay.platform.wallets.register', $wallet->uuid)">{{ __('injongepay::injongepay.register_at_bank') }}</x-common.action-button>
                @endif
                @if ($bankConfigured && ! in_array($wallet->bank_status, ['NOT_REGISTERED'], true))
                    <x-common.action-button :action="route('injongepay.platform.wallets.sync', $wallet->uuid)">{{ __('injongepay::injongepay.sync_from_bank') }}</x-common.action-button>
                @endif
            @endif
            <x-common.button href="{{ route('injongepay.platform.wallets.index') }}" variant="secondary" size="sm">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>
    @include('injongepay::partials.test-mode')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach ([[__('injongepay::injongepay.available'), Money::format($wallet->available_balance)], [__('injongepay::injongepay.pending'), Money::format($wallet->pending_balance)], [__('injongepay::injongepay.bank_status'), null]] as [$label, $value])
                    <div class="bg-card border border-line rounded-xl p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $label }}</p>
                        <div class="text-xl font-display font-bold text-ink mt-1">{!! $value ?? view('injongepay::partials.bank-status', ['wallet' => $wallet])->render() !!}</div>
                    </div>
                @endforeach
            </div>

            @if ($wallet->isFrozen())
                <div class="bg-card border border-danger/40 rounded-xl p-5 space-y-3">
                    <h2 class="font-display font-bold text-danger">{{ __('injongepay::injongepay.wallet_frozen') }}</h2>
                    <p class="text-sm text-body">{{ $wallet->frozen_reason }}</p>
                    <p class="text-xs text-muted">{{ GeneralLibrary::formatDateTime($wallet->frozen_at) }}</p>
                    @if (AuthHelper::can('approve injongepay_platform.reconciliation'))
                        <form action="{{ route('injongepay.platform.wallets.unfreeze', $wallet->uuid) }}" method="POST" class="space-y-3">
                            @csrf @method('PUT')
                            <x-common.input name="reason" :id="'unfreeze-reason'" :label="__('injongepay::injongepay.unfreeze_reason')" required compact />
                            <x-common.button type="submit" size="sm" variant="danger">{{ __('injongepay::injongepay.unfreeze') }}</x-common.button>
                        </form>
                    @endif
                </div>
            @endif
            @if ($wallet->bank_error)
                <div class="rounded-xl border border-danger/40 bg-danger/5 p-4 text-sm">
                    <p class="font-semibold text-danger">{{ __('injongepay::injongepay.bank_problem') }}</p>
                    <p class="text-body mt-1">{{ $wallet->bank_error }}</p>
                    @if ($wallet->bank_error_at)<p class="text-xs text-muted mt-1">{{ GeneralLibrary::formatDateTime($wallet->bank_error_at) }}</p>@endif
                </div>
            @endif
            @if ($wallet->bank_update_pending_at)
                <div class="rounded-xl border border-warning/40 bg-warning/5 p-4 text-sm text-body">{{ __('injongepay::injongepay.bank_update_pending', ['when' => GeneralLibrary::formatDateTime($wallet->bank_update_pending_at)]) }}</div>
            @endif

            <x-common.info-card :title="__('injongepay::injongepay.sent_to_bank')" :rows="[
                [__('body.business_name'), $details['businessName'] ?? '—'],
                [__('injongepay::injongepay.kyc_contact_person'), $details['businessOwner'] ?? '—'],
                [__('injongepay::injongepay.kyc_tin'), $details['taxIdentificationNumber'] ?? '—'],
                [__('injongepay::injongepay.kyc_contact_phone'), $details['contactPhone'] ?? '—'],
                [__('injongepay::injongepay.kyc_contact_email'), $details['contactEmail'] ?? '—'],
                [__('injongepay::injongepay.bank_registered_name'), $wallet->bank_registered_name ?? '—'],
                [__('injongepay::injongepay.bank_synced_at'), $wallet->bank_synced_at ? GeneralLibrary::formatDateTime($wallet->bank_synced_at) : '—'],
            ]" />

            <div class="bg-card border border-line rounded-xl overflow-hidden">
                <h2 class="font-display font-bold text-ink px-5 pt-4">{{ __('injongepay::injongepay.bank_calls') }}</h2>
                <table class="w-full text-sm mt-2">
                    <tbody class="divide-y divide-line">
                        @forelse ($calls as $call)
                            <tr>
                                <td class="px-5 py-2 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($call->created_at) }}</td>
                                <td class="px-5 py-2 font-mono">{{ $call->method }} {{ $call->endpoint }}</td>
                                <td class="px-5 py-2">@include('injongepay::partials.outcome', ['outcome' => $call->outcome])</td>
                                <td class="px-5 py-2 text-right"><a class="text-brand font-semibold" href="{{ route('injongepay.platform.bank-calls.show', $call) }}">{{ __('body.view') }}</a></td>
                            </tr>
                        @empty
                            <tr><td class="px-5 py-6 text-muted">{{ __('injongepay::injongepay.no_bank_calls') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-card border border-line rounded-xl overflow-hidden">
                <h2 class="font-display font-bold text-ink px-5 pt-4">{{ __('injongepay::injongepay.ledger') }}</h2>
                <table class="w-full text-sm mt-2">
                    <tbody class="divide-y divide-line">
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="px-5 py-2 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($entry->created_at) }}</td>
                                <td class="px-5 py-2 font-mono">{{ $entry->reference }}</td>
                                <td class="px-5 py-2">{{ __('injongepay::injongepay.source_'.$entry->source) }} · {{ __('injongepay::injongepay.'.$entry->bucket) }}</td>
                                <td class="px-5 py-2 text-right {{ $entry->direction === 'credit' ? 'text-success' : 'text-danger' }}">{{ $entry->direction === 'credit' ? '+' : '−' }}{{ Money::format($entry->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-5 py-6 text-muted">{{ __('injongepay::injongepay.no_entries') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            @if (! $wallet->isInternal() && AuthHelper::can('update injongepay_platform.wallet'))
                <form action="{{ route('injongepay.platform.wallets.update', $wallet->uuid) }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
                    @csrf @method('PUT')
                    <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.wallet_details') }}</h2>
                    <p class="text-sm text-muted">{{ __('injongepay::injongepay.wallet_details_hint') }}</p>
                    @include('injongepay::platform.wallets._details', ['wallet' => $wallet])
                    <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                </form>

                <form action="{{ route('injongepay.platform.wallets.toggle', $wallet->uuid) }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-3">
                    @csrf @method('PUT')
                    <h2 class="font-display font-bold text-ink">{{ $wallet->is_enabled ? __('injongepay::injongepay.wallet_disable') : __('injongepay::injongepay.wallet_enable') }}</h2>
                    <p class="text-sm text-muted">{{ $wallet->is_enabled ? __('injongepay::injongepay.wallet_disable_hint') : __('injongepay::injongepay.wallet_enable_hint') }}</p>
                    <x-common.input name="reason" :label="__('injongepay::injongepay.reason')" required compact />
                    <x-common.button type="submit" size="sm" :variant="$wallet->is_enabled ? 'danger' : 'primary'">{{ $wallet->is_enabled ? __('injongepay::injongepay.wallet_disable') : __('injongepay::injongepay.wallet_enable') }}</x-common.button>
                </form>
            @endif

            @if (! $wallet->isInternal() && $wallet->bank_status !== 'CLOSED' && AuthHelper::can('delete injongepay_platform.wallet'))
                <form action="{{ route('injongepay.platform.wallets.close', $wallet->uuid) }}" method="POST" class="bg-card border border-danger/40 rounded-xl p-5 space-y-3">
                    @csrf
                    <h2 class="font-display font-bold text-danger">{{ __('injongepay::injongepay.wallet_close') }}</h2>
                    <p class="text-sm text-muted">{{ __('injongepay::injongepay.wallet_close_hint') }}</p>
                    <x-common.input name="reason" :id="'close-reason'" :label="__('injongepay::injongepay.reason')" required compact />
                    <x-common.button type="submit" size="sm" variant="danger">{{ __('injongepay::injongepay.wallet_close') }}</x-common.button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
