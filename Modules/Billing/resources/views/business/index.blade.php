@php use Modules\Billing\Support\Money; @endphp
<x-layouts.app :title="__('billing::billing.my_subscription')">
    <x-common.page-header :title="__('billing::billing.my_subscription')" :description="__('billing::billing.my_subscription_description')">
        <x-slot:actions>
            @if (! $isPlatformOwner && \App\Helpers\AuthHelper::can('manage billing.subscription'))
                <x-common.button href="{{ route('billing.plans') }}" size="sm">{{ $subscription ? __('billing::billing.change_plan') : __('billing::billing.choose_plan') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="space-y-6">
        @if ($isPlatformOwner)
            <div class="rounded-xl border border-brand/30 bg-brand-light p-5 text-sm text-brand-dark">{{ __('billing::billing.platform_owner_note') }}</div>
        @endif

        {{-- Current plan --}}
        <div class="bg-white border border-line rounded-xl p-5 sm:p-6">
            @if ($subscription)
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <h2 class="font-display font-bold text-xl text-ink">{{ $subscription->name() }}</h2>
                            <x-common.badge :color="$subscription->status->color()">{{ $subscription->status->label() }}</x-common.badge>
                        </div>
                        <p class="text-muted">{{ Money::format($subscription->price()) }} / {{ $subscription->interval()->label($subscription->intervalCount()) }}</p>

                        @switch($subscription->status->value)
                            @case('trialing')
                                <p class="text-sm text-body">{{ __('billing::billing.trial_until', ['date' => $subscription->trial_ends_at?->format('d M Y')]) }}</p>
                                @break
                            @case('active')
                                <p class="text-sm text-body">{{ $subscription->cancel_at_period_end ? __('billing::billing.ends_on', ['date' => $subscription->current_period_end?->format('d M Y')]) : __('billing::billing.renews_on', ['date' => $subscription->current_period_end?->format('d M Y')]) }}</p>
                                @break
                            @case('past_due')
                                <p class="text-sm text-warning font-medium">{{ __('billing::billing.past_due_note', ['date' => $subscription->grace_ends_at?->format('d M Y')]) }}</p>
                                @break
                            @case('suspended')
                                <p class="text-sm text-danger font-medium">{{ __('billing::billing.suspended_note') }}</p>
                                @break
                            @case('pending')
                                <p class="text-sm text-warning font-medium">{{ __('billing::billing.pending_note') }}</p>
                                @break
                        @endswitch

                        @if ($subscription->scheduledPackage)
                            <p class="text-sm text-body">{{ __('billing::billing.scheduled_change', ['name' => $subscription->scheduledPackage->name, 'date' => $subscription->current_period_end?->format('d M Y')]) }}</p>
                        @endif
                    </div>

                    @if (\App\Helpers\AuthHelper::can('manage billing.subscription'))
                        <div class="flex flex-wrap gap-2">
                            @if ($subscription->cancel_at_period_end)
                                <form action="{{ route('billing.resume') }}" method="POST">@csrf<x-common.button type="submit" size="sm">{{ __('billing::billing.resume') }}</x-common.button></form>
                            @elseif (in_array($subscription->status->value, ['trialing', 'active', 'past_due', 'pending'], true))
                                <form action="{{ route('billing.cancel') }}" method="POST">
                                    @csrf
                                    <x-common.button type="submit" size="sm" variant="secondary"
                                        data-confirm data-confirm-title="{{ __('billing::billing.cancel_title') }}"
                                        data-confirm-message="{{ __('billing::billing.cancel_message') }}" data-confirm-variant="danger">
                                        {{ __('billing::billing.cancel') }}
                                    </x-common.button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-5 pt-5 border-t border-line">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted mb-2">{{ __('billing::billing.included_modules') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @forelse ($moduleNames as $name)
                            <x-common.badge color="success">✓ {{ $name }}</x-common.badge>
                        @empty
                            <span class="text-sm text-muted">—</span>
                        @endforelse
                    </div>
                </div>
            @elseif (! $isPlatformOwner)
                <x-common.empty-state :title="__('billing::billing.no_subscription')" :description="__('billing::billing.no_subscription_hint')">
                    <x-slot:action>
                        <x-common.button href="{{ route('billing.plans') }}">{{ __('billing::billing.choose_plan') }}</x-common.button>
                    </x-slot:action>
                </x-common.empty-state>
            @endif
        </div>

        {{-- Usage --}}
        @if ($usage->isNotEmpty())
            <div class="bg-white border border-line rounded-xl p-5 sm:p-6">
                <h2 class="font-display font-bold text-ink mb-4">{{ __('billing::billing.usage') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($usage as $row)
                        @php $pct = $row['limit'] ? min(100, round($row['used'] / $row['limit'] * 100)) : 0; @endphp
                        <div class="rounded-xl bg-surface border border-line p-4">
                            <div class="flex justify-between text-sm">
                                <span class="font-semibold text-ink">{{ ucfirst($row['label']) }}</span>
                                <span class="text-muted">{{ $row['used'] }} / {{ $row['limit'] ?? '∞' }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-white border border-line mt-3 overflow-hidden">
                                <div class="h-full {{ $pct >= 100 ? 'bg-danger' : ($pct >= 80 ? 'bg-warning' : 'bg-brand') }}" style="width: {{ $row['limit'] ? $pct : 5 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Invoices to pay --}}
        @if ($openInvoices->isNotEmpty())
            <div class="bg-white border border-warning/40 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line">
                    <h2 class="font-display font-bold text-ink">{{ __('billing::billing.invoices_to_pay') }}</h2>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($openInvoices as $invoice)
                        <li class="px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-ink">{{ $invoice->number }} · {{ Money::format(Money::fromCents($invoice->balanceCents()), $invoice->currency) }}</p>
                                <p class="text-sm {{ $invoice->isOverdue() ? 'text-danger' : 'text-muted' }}">{{ __('billing::billing.due_on', ['date' => $invoice->due_at?->format('d M Y')]) }}</p>
                            </div>
                            <x-common.button href="{{ route('billing.invoices.show', $invoice) }}" size="sm">{{ __('billing::billing.view_and_pay') }}</x-common.button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            {{-- History --}}
            <div class="xl:col-span-2 space-y-6">
                <div class="bg-white border border-line rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('billing::billing.invoice_history') }}</h2></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                                    <th class="px-5 py-3 font-semibold">{{ __('billing::billing.invoice') }}</th>
                                    <th class="px-5 py-3 font-semibold">{{ __('billing::billing.issued') }}</th>
                                    <th class="px-5 py-3 font-semibold">{{ __('billing::billing.total') }}</th>
                                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @forelse ($invoiceHistory as $invoice)
                                    <tr>
                                        <td class="px-5 py-3 font-medium text-ink whitespace-nowrap">{{ $invoice->number }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap text-muted">{{ $invoice->issued_at?->format('d M Y') }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap">{{ Money::format($invoice->total, $invoice->currency) }}</td>
                                        <td class="px-5 py-3"><x-common.badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-common.badge></td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap">
                                            <x-common.action-button :href="route('billing.invoices.show', $invoice)">{{ __('body.view') }}</x-common.action-button>
                                            <x-common.action-button :href="route('billing.invoices.pdf', $invoice)" color="muted">PDF</x-common.action-button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-5 py-8 text-center text-muted">{{ __('billing::billing.no_invoices') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white border border-line rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('billing::billing.payments') }}</h2></div>
                    <x-billing::payments-table :payments="$payments" />
                </div>
            </div>

            {{-- Billing details --}}
            <div class="space-y-6">
                @if (filled($instructions))
                    <div class="bg-white border border-line rounded-xl p-5">
                        <h2 class="font-display font-bold text-ink mb-2">{{ __('billing::billing.how_to_pay') }}</h2>
                        <p class="text-sm text-body leading-relaxed">{!! nl2br(e($instructions)) !!}</p>
                    </div>
                @endif

                <form action="{{ route('billing.account') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                    @csrf @method('PUT')
                    <div>
                        <h2 class="font-display font-bold text-ink">{{ __('billing::billing.details') }}</h2>
                        <p class="text-sm text-muted mt-1">{{ __('billing::billing.details_hint') }}</p>
                    </div>
                    <x-common.input name="billing_name" :label="__('billing::billing.name')" :value="old('billing_name', $account->billing_name)" />
                    <x-common.input name="billing_email" type="email" :label="__('body.email_address')" :value="old('billing_email', $account->billing_email)" />
                    <x-common.input name="tax_id" :label="__('billing::billing.tax_id')" :value="old('tax_id', $account->tax_id)" />
                    <x-common.textarea name="address" :label="__('body.address')" :value="old('address', $account->address)" :rows="2" />
                    @if ((float) $account->credit_balance > 0)
                        <p class="text-sm text-success">{{ __('billing::billing.credit_balance', ['amount' => Money::format($account->credit_balance)]) }}</p>
                    @endif
                    @if (\App\Helpers\AuthHelper::can('manage billing.subscription'))
                        <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
