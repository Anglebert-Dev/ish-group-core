@php use Modules\Billing\Support\Money; @endphp
<x-layouts.app :title="__('billing::billing.subscriptions')">
    @include('billing::admin.partials.nav')

    <x-common.page-header :title="__('billing::billing.subscriptions')" :description="__('billing::billing.subscriptions_description')" />

    <x-common.filter-panel :action="route('superadmin.billing.subscriptions.index')" :open="array_filter($filters) !== []">
        <x-common.input name="q" :label="__('body.search')" :value="$filters['q'] ?? ''" :placeholder="__('body.business_name')" compact />
        <x-common.select name="status" :label="__('body.status')" :value="$filters['status'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all')] + collect(\Modules\Billing\Enums\SubscriptionStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        <x-common.select name="package" :label="__('billing::billing.package')" :value="$filters['package'] ?? ''" compact hide-placeholder :options="['' => __('body.all')] + $packages->all()" />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('body.business') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('billing::billing.package') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('billing::billing.price') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('billing::billing.paid_until') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.created') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($subscriptions as $subscription)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink">{{ $subscription->business?->name }}</td>
                        <td class="px-5 py-3.5">{{ $subscription->name() }}</td>
                        <td class="px-5 py-3.5"><x-common.badge :color="$subscription->status->color()">{{ $subscription->status->label() }}</x-common.badge></td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ Money::format($subscription->price()) }} / {{ $subscription->interval()->label($subscription->intervalCount()) }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-muted">{{ $subscription->current_period_end?->format('d M Y') ?? '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-muted">{{ $subscription->created_at?->format('d M Y') }}</td>
                        <td class="px-5 py-3.5"><x-common.action-button :href="route('superadmin.billing.subscriptions.show', $subscription)">{{ __('body.manage') }}</x-common.action-button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('billing::billing.no_subscriptions') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$subscriptions" class="border-t border-line" />
    </div>
</x-layouts.app>
