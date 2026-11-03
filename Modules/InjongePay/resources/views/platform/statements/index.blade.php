@php use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepay::injongepay.statements')">
    <x-common.page-header :title="__('injongepay::injongepay.statements')" :description="__('injongepay::injongepay.statements_description')">
        <x-slot:actions>
            <x-common.button href="{{ route('injongepay.platform.reconciliation.index') }}" variant="secondary" size="sm">{{ __('injongepay::injongepay.menu_reconciliation') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>
    @include('injongepay::partials.test-mode')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2">
            <x-common.table :headers="[__('injongepay::injongepay.period'), __('injongepay::injongepay.file'), __('injongepay::injongepay.lines'), __('body.status'), __('injongepay::injongepay.checked_by'), '']">
                @forelse ($checks as $check)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3 whitespace-nowrap">{{ $check->period_from->format('d M Y') }} – {{ $check->period_to->format('d M Y') }}</td>
                        <td class="px-5 py-3 text-muted">{{ $check->file_name }}</td>
                        <td class="px-5 py-3">{{ $check->lines }}</td>
                        <td class="px-5 py-3"><x-common.badge :color="$check->agrees ? 'success' : 'danger'">{{ $check->agrees ? __('injongepay::injongepay.agrees') : __('injongepay::injongepay.differs') }}</x-common.badge></td>
                        <td class="px-5 py-3 text-muted">{{ $check->uploaded_by_name }} · {{ GeneralLibrary::formatDateTime($check->created_at) }}</td>
                        <td class="px-5 py-3 text-right"><a class="text-brand font-semibold" href="{{ route('injongepay.platform.statements.show', $check) }}">{{ __('body.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('injongepay::injongepay.no_statements') }}</td></tr>
                @endforelse
            </x-common.table>
            <x-common.pagination :paginator="$checks" class="mt-4" />
        </div>
        @if (\App\Helpers\AuthHelper::can('update injongepay_platform.reconciliation'))
            <form action="{{ route('injongepay.platform.statements.store') }}" method="POST" enctype="multipart/form-data" class="bg-card border border-line rounded-xl p-5 space-y-4">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.statement_upload') }}</h2>
                <p class="text-sm text-muted">{{ __('injongepay::injongepay.statement_upload_hint') }}</p>
                <x-common.input name="from" type="date" :label="__('injongepay::injongepay.from')" :value="old('from', now()->subMonthNoOverflow()->startOfMonth()->toDateString())" required />
                <x-common.input name="to" type="date" :label="__('injongepay::injongepay.to')" :value="old('to', now()->subMonthNoOverflow()->endOfMonth()->toDateString())" required />
                <x-common.file-input name="statement" :label="__('injongepay::injongepay.statement_file')" accept=".csv,text/csv" required />
                <x-common.button type="submit">{{ __('injongepay::injongepay.statement_check') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
