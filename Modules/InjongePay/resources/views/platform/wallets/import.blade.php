<x-layouts.app :title="__('injongepay::injongepay.wallet_import')">
    <x-common.page-header :title="__('injongepay::injongepay.wallet_import')" :description="__('injongepay::injongepay.wallet_import_description', ['max' => $max])">
        <x-slot:actions>
            <x-common.button href="{{ route('injongepay.platform.wallets.import.template') }}" variant="secondary" size="sm">{{ __('injongepay::injongepay.import_template') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>
    @include('injongepay::partials.test-mode')

    @if ($rowErrors = session('import_errors'))
        <div class="bg-card border border-line rounded-xl p-5 mb-4 max-w-2xl">
            <h3 class="font-semibold text-ink mb-2">{{ __('injongepay::injongepay.import_fix_rows') }}</h3>
            <ul class="text-sm space-y-1">
                @foreach (array_slice($rowErrors, 0, 100, true) as $line => $problems)
                    <li><span class="font-mono text-muted">{{ __('injongepay::injongepay.import_line', ['line' => $line]) }}</span> {{ implode(' ', $problems) }}</li>
                @endforeach
            </ul>
            @if (count($rowErrors) > 100)
                <p class="text-sm text-muted mt-2">{{ __('injongepay::injongepay.import_more_errors', ['count' => count($rowErrors) - 100]) }}</p>
            @endif
        </div>
    @endif

    <form action="{{ route('injongepay.platform.wallets.import.store') }}" method="POST" enctype="multipart/form-data" class="bg-card border border-line rounded-xl p-5 sm:p-6 space-y-4 max-w-2xl">
        @csrf
        <x-common.select name="business" :label="__('body.business')" :options="$businesses" :value="old('business', $business)" required searchable />
        <x-common.file-input name="file" :label="__('injongepay::injongepay.import_file')" accept=".csv,text/csv" required />
        <p class="text-sm text-muted">{{ __('injongepay::injongepay.import_columns') }}</p>
        <p class="text-sm text-muted">{{ __('injongepay::injongepay.import_how_bank') }}</p>
        <div class="flex gap-3">
            <x-common.button type="submit">{{ __('injongepay::injongepay.import_submit') }}</x-common.button>
            <x-common.button href="{{ route('injongepay.platform.wallets.index') }}" variant="secondary">{{ __('body.cancel') }}</x-common.button>
        </div>
    </form>
</x-layouts.app>
