<x-layouts.app :title="__('updates.title')">
    <x-superadmin.nav />

    <x-common.page-header :title="__('updates.title')" :description="__('updates.description')">
        <x-slot:actions>
            <form action="{{ route('superadmin.updates.check') }}" method="POST">
                @csrf
                <x-common.button type="submit" size="sm" variant="secondary">{{ __('updates.check_now') }}</x-common.button>
            </form>
        </x-slot:actions>
    </x-common.page-header>

    @if ($lastError)
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 text-red-800 px-5 py-4 text-sm">{{ $lastError }}</div>
    @endif
    @unless ($writable)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-5 py-4 text-sm">{{ __('updates.modules_not_writable') }}</div>
    @endunless

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line flex items-center justify-between gap-3">
                    <p class="font-semibold text-ink">{{ __('updates.available') }}</p>
                    @if ($offers && $writable)
                        <form action="{{ route('superadmin.updates.install') }}" method="POST">
                            @csrf
                            <x-common.button type="submit" size="sm" data-confirm data-confirm-title="{{ __('updates.install_confirm') }}">{{ __('updates.install_all') }}</x-common.button>
                        </form>
                    @endif
                </div>
                <ul class="divide-y divide-line text-sm">
                    @forelse ($offers as $offer)
                        <li class="px-5 py-3.5 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-ink">
                                    {{ $offer['module'] }}
                                    <span class="font-mono text-muted">{{ $offer['from_version'] ? $offer['from_version'].' → ' : '' }}{{ $offer['version'] }}</span>
                                    @if ($offer['action'] === 'install')<x-common.badge color="brand">{{ __('updates.new_module') }}</x-common.badge>@endif
                                </p>
                                @if (! empty($offer['changelog']))<p class="text-muted mt-1 whitespace-pre-line">{{ $offer['changelog'] }}</p>@endif
                            </div>
                            @if ($writable)
                                <x-common.action-button :action="route('superadmin.updates.install')" data-confirm data-confirm-title="{{ __('updates.install_confirm') }}">
                                    <x-slot:fields><input type="hidden" name="module" value="{{ $offer['module'] }}"></x-slot:fields>
                                    {{ __('updates.install') }}
                                </x-common.action-button>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-muted">{{ __('updates.none_available') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><p class="font-semibold text-ink">{{ __('updates.history') }}</p></div>
                <ul class="divide-y divide-line text-sm">
                    @forelse ($history as $item)
                        <li class="px-5 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-ink">{{ $item['module'] }} <span class="font-mono text-muted">{{ $item['from_version'] ? $item['from_version'].' → ' : '' }}{{ $item['to_version'] }}</span></span>
                                <x-common.badge :color="$item['status'] === 'success' ? 'success' : 'danger'">{{ __('updates.result_'.$item['status']) }}</x-common.badge>
                            </div>
                            <div class="text-xs text-muted mt-0.5">@datetime(\Carbon\Carbon::parse($item['at']))</div>
                            @if ($item['error'])<div class="text-xs text-danger mt-1 break-words">{{ $item['error'] }}</div>@endif
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-muted">{{ __('updates.no_history') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="bg-white border border-line rounded-xl p-5 text-sm space-y-3">
            <div>
                <p class="text-muted">{{ __('updates.mode') }}</p>
                <p class="font-medium text-ink">{{ __('updates.mode_'.($mode === 'auto' ? 'auto' : 'manual')) }}</p>
                <p class="text-xs text-muted">{{ __('updates.mode_hint') }}</p>
            </div>
            <div>
                <p class="text-muted">{{ __('updates.last_check') }}</p>
                <p class="font-medium text-ink">{{ $lastCheck ? \Carbon\Carbon::parse($lastCheck)->diffForHumans() : __('updates.never') }}</p>
            </div>
            <div>
                <p class="text-muted">{{ __('updates.version') }}</p>
                <p class="font-mono text-ink">{{ $version }}</p>
            </div>
        </div>
    </div>
</x-layouts.app>
