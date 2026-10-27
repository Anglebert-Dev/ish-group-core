@php
    $contacts = $corporateCustomer->contacts->keyBy('type');
    $tabs = [
        ['id' => 'overview', 'label' => __('body.overview'), 'icon' => 'M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h1m-1 4h1m-1 4h1'],
        ['id' => 'contacts', 'label' => __('body.contacts'), 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4a4 4 0 10-8 0 4 4 0 008 0zm6 0a4 4 0 10-8 0 4 4 0 008 0z'],
    ];
@endphp

<x-layouts.app title="{{ $corporateCustomer->company_name }}">
    <div data-details-page>
        <x-common.details.header
            :title="$corporateCustomer->company_name"
            :subtitle="__('body.customer_details')"
            :back-href="route('customers.corporate.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="ucfirst($corporateCustomer->status->value)" />
            </x-slot:meta>
            <x-slot:actions>
                <x-common.button href="{{ route('customers.corporate.edit', $corporateCustomer) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.tin')" :value="$corporateCustomer->tin ?? '—'" />
            <x-common.details.summary-item :label="__('body.email_address')" :value="$corporateCustomer->email ?? '—'" />
            <x-common.details.summary-item :label="__('body.phone')" :value="$corporateCustomer->phone ?? '—'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="ucfirst($corporateCustomer->status->value)" />
            </x-common.details.summary-item>
            <x-slot:tabs>
                <x-common.details.tabs :tabs="$tabs" active="overview" />
            </x-slot:tabs>
        </x-common.details.summary>

        <x-common.status-update
            :action="route('customers.corporate.status', $corporateCustomer)"
            :value="$corporateCustomer->status->value"
            :options="\App\Enums\CorporateCustomerStatus::cases()"
            :can="\App\Helpers\AuthHelper::can('update customers.corporate_customer')"
        />


        <div data-details-panel="overview" class="space-y-6">
            <x-common.details.section :title="__('body.overview')" icon="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h1m-1 4h1m-1 4h1">
                <x-common.details.grid>
                    <x-common.details.field :label="__('body.tin')">{{ $corporateCustomer->tin ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.status')">
                        <x-common.status :status="ucfirst($corporateCustomer->status->value)" />
                    </x-common.details.field>
                    <x-common.details.field :label="__('body.email_address')">{{ $corporateCustomer->email ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.phone')">{{ $corporateCustomer->phone ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.payment_terms')">{{ $corporateCustomer->payment_terms ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.credit_limit')">{{ $corporateCustomer->credit_limit !== null ? number_format($corporateCustomer->credit_limit, 2) : '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.address')" :span="2">
                        <span class="whitespace-pre-line">{{ $corporateCustomer->address ?? '—' }}</span>
                    </x-common.details.field>
                    <x-common.details.field :label="__('body.billing_address')" :span="2">
                        <span class="whitespace-pre-line">{{ $corporateCustomer->billing_address ?? '—' }}</span>
                    </x-common.details.field>
                    <x-common.details.field :label="__('body.notes')" :span="2">
                        <span class="whitespace-pre-line">{{ $corporateCustomer->notes ?? '—' }}</span>
                    </x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>
        </div>

        <div data-details-panel="contacts" class="hidden space-y-6">
            <x-common.details.section :title="__('body.contacts')" icon="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4a4 4 0 10-8 0 4 4 0 008 0zm6 0a4 4 0 10-8 0 4 4 0 008 0z" tone="ink">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach (['primary' => __('body.primary_contact'), 'booking' => __('body.booking_contact'), 'finance' => __('body.finance_contact')] as $type => $label)
                        <div class="p-4 bg-surface rounded-lg border border-line">
                            <p class="text-xs font-semibold text-muted uppercase tracking-wide mb-2">{{ $label }}</p>
                            @if ($contacts->has($type))
                                <p class="text-sm text-ink font-semibold">{{ $contacts[$type]->name }}</p>
                                <p class="text-xs text-muted mt-1">{{ $contacts[$type]->email ?? '—' }}</p>
                                <p class="text-xs text-muted">{{ $contacts[$type]->phone ?? '—' }}</p>
                            @else
                                <p class="text-sm text-muted">—</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-common.details.section>
        </div>
    </div>
</x-layouts.app>
