<x-layouts.app :title="__('billing::billing.payments')">
    @include('billing::admin.partials.nav')

    <x-common.page-header :title="__('billing::billing.payments')" :description="__('billing::billing.payments_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('superadmin.billing.payments.export', ['pdf'] + request()->query())"
                :excel="route('superadmin.billing.payments.export', ['excel'] + request()->query())"
            />
        </x-slot:actions>
    </x-common.page-header>

    <div class="flex flex-wrap gap-1.5 mb-4">
        @foreach (['pending' => __('billing::billing.payment_pending'), 'approved' => __('billing::billing.payment_approved'), 'rejected' => __('billing::billing.payment_rejected'), 'all' => __('body.all')] as $key => $label)
            <a href="{{ route('superadmin.billing.payments.index', ['status' => $key]) }}"
               class="rounded-lg px-3 py-1.5 text-sm font-medium border {{ $status === $key ? 'bg-ink text-white border-ink' : 'bg-white border-line text-body hover:border-ink' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <x-billing::payments-table :payments="$payments" admin />
        <x-common.pagination :paginator="$payments" class="border-t border-line" />
    </div>
</x-layouts.app>
