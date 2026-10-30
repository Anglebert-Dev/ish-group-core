@php $can = fn ($p) => \App\Helpers\AuthHelper::can($p); @endphp
<x-layouts.app :title="__('savings::savings.data')">
    <x-common.page-header :title="__('savings::savings.data')" />
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        @if ($can('create savings.data'))
            <form action="{{ route('savings.data.preview') }}" method="POST" enctype="multipart/form-data" class="bg-white border border-line rounded-xl p-5 space-y-3">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.import') }}</h2>
                <p class="text-xs text-muted">{{ __('savings::savings.import_hint') }}</p>
                <x-common.select name="account" :label="__('savings::savings.account')" :options="$accounts->mapWithKeys(fn ($a) => [$a->uuid => $a->name])->all()" hide-placeholder required />
                <input type="file" name="file" accept=".csv,text/csv" required class="text-sm">
                <x-common.button type="submit" size="sm">{{ __('savings::savings.preview') }}</x-common.button>
                @foreach ($batches as $b)
                    <a href="{{ route('savings.data.batch', $b) }}" class="block text-xs text-brand">{{ $b->created_at->format('d/m H:i') }} · {{ $b->filename }} · {{ $b->status }} ({{ $b->imported }})</a>
                @endforeach
            </form>
        @endif
        @if ($can('read savings.data'))
            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.backup') }}</h2>
                <p class="text-xs text-muted">{{ __('savings::savings.backup_hint') }}</p>
                <x-common.button href="{{ route('savings.data.backup') }}" size="sm">{{ __('savings::savings.download_backup') }}</x-common.button>
            </div>
        @endif
        @if ($can('update savings.data'))
            <form action="{{ route('savings.data.restore') }}" method="POST" enctype="multipart/form-data" class="bg-white border border-danger/40 rounded-xl p-5 space-y-3">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.restore') }}</h2>
                <p class="text-xs text-muted">{{ __('savings::savings.restore_backup_hint') }}</p>
                <input type="file" name="file" accept=".json,application/json" required class="text-sm">
                <x-common.select name="mode" :label="__('savings::savings.restore')" :options="['merge' => __('savings::savings.mode_merge'), 'replace' => __('savings::savings.mode_replace')]" hide-placeholder />
                <x-common.button type="submit" variant="secondary" size="sm" data-confirm data-confirm-title="{{ __('savings::savings.restore') }}?" data-confirm-variant="danger">{{ __('savings::savings.restore') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
