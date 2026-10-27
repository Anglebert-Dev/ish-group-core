<x-layouts.app title="{{ $supplier->name }}">
    <div data-details-page>
        <x-common.details.header
            :title="$supplier->name"
            :subtitle="__('body.supplier_details')"
            :back-href="route('finance.suppliers.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$supplier->status->label()" />
            </x-slot:meta>
            <x-slot:actions>
                <x-common.button href="{{ route('finance.suppliers.edit', $supplier) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.supplier_type')" :value="$supplier->type->label()" />
            <x-common.details.summary-item :label="__('body.contact_name')" :value="$supplier->contact_name ?? '—'" />
            <x-common.details.summary-item :label="__('body.phone')" :value="$supplier->phone ?? '—'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$supplier->status->label()" />
            </x-common.details.summary-item>
        </x-common.details.summary>

        <x-common.status-update
            :action="route('finance.suppliers.status', $supplier)"
            :value="$supplier->status->value"
            :options="\App\Enums\SupplierStatus::cases()"
            :can="\App\Helpers\AuthHelper::can('update finance.supplier')"
        />


        <x-common.details.section
            :title="__('body.overview')"
            icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
        >
            <x-common.details.grid>
                <x-common.details.field :label="__('body.supplier_type')">{{ $supplier->type->label() }}</x-common.details.field>
                <x-common.details.field :label="__('body.status')">
                    <x-common.status :status="$supplier->status->label()" />
                </x-common.details.field>
                <x-common.details.field :label="__('body.contact_name')">{{ $supplier->contact_name ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.phone')">{{ $supplier->phone ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.email_address')">{{ $supplier->email ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.tin')">{{ $supplier->tin ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.address')" :span="2">
                    <span class="whitespace-pre-line">{{ $supplier->address ?? '—' }}</span>
                </x-common.details.field>
                <x-common.details.field :label="__('body.notes')" :span="2">
                    <span class="whitespace-pre-line">{{ $supplier->notes ?? '—' }}</span>
                </x-common.details.field>
            </x-common.details.grid>
        </x-common.details.section>
    </div>
</x-layouts.app>
