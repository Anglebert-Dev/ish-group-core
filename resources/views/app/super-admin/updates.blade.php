<x-layouts.app :title="__('updates.title')">
    <x-superadmin.nav />

    <x-common.page-header :title="__('updates.title')" :description="__('updates.description')">
        @if ($linked)
            <x-slot:actions>
                <form action="{{ route('superadmin.updates.check') }}" method="POST">
                    @csrf
                    <x-common.button type="submit" size="sm" variant="secondary">{{ __('updates.check_now') }}</x-common.button>
                </form>
            </x-slot:actions>
        @endif
    </x-common.page-header>

    @if ($linked && $support && $support['until'])
        @php $until = \Carbon\Carbon::parse($support['until']); @endphp
        @if (! $support['active'])
            <div class="mb-6 rounded-xl border border-warning/30 bg-warning/10 text-ink px-5 py-4 text-sm">
                <p class="font-semibold">{{ __('updates.support_ended', ['date' => $until->format('d M Y')]) }}</p>
                <p class="mt-1">{{ __('updates.support_ended_hint') }}</p>
            </div>
        @elseif ($until->diffInDays(today(), true) <= 30)
            <div class="mb-6 rounded-xl border border-warning/30 bg-warning/10 text-ink px-5 py-4 text-sm">{{ __('updates.support_ending', ['date' => $until->format('d M Y')]) }}</div>
        @endif
    @endif

    @if ($lastError)
        <div class="mb-6 rounded-xl border border-danger/30 bg-danger/10 text-ink px-5 py-4 text-sm">{{ $lastError }}</div>
    @endif
    @unless ($writable)
        <div class="mb-6 rounded-xl border border-warning/30 bg-warning/10 text-ink px-5 py-4 text-sm">{{ __('updates.modules_not_writable') }}</div>
    @endunless

    @unless ($linked)
        <div class="mb-6 rounded-xl border border-line bg-card px-5 py-4 text-sm text-muted">{{ __('updates.not_linked_hint') }}</div>
    @endunless

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            @if ($coreOffer)
                <div class="bg-card border border-warning/30 rounded-xl overflow-hidden">
                    <div class="px-5 py-4 bg-warning/10 border-b border-warning/30 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-ink">{{ __('updates.core_title') }}
                                <span class="font-mono text-muted">{{ $coreOffer['from_version'] ? $coreOffer['from_version'].' → ' : '' }}{{ $coreOffer['version'] }}</span></p>
                            <p class="text-sm text-ink mt-1 max-w-2xl">{{ __('updates.core_hint') }}</p>
                            @if (! empty($coreOffer['changelog']))<p class="text-sm text-muted mt-2 whitespace-pre-line">{{ $coreOffer['changelog'] }}</p>@endif
                        </div>
                        <form action="{{ route('superadmin.updates.install-core') }}" method="POST">
                            @csrf
                            <x-common.button type="submit" size="sm" data-confirm data-confirm-title="{{ __('updates.core_confirm') }}">{{ __('updates.install') }}</x-common.button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="bg-card border border-line rounded-xl overflow-hidden">
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

            <div class="bg-card border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><p class="font-semibold text-ink">{{ __('updates.history') }}</p></div>
                <ul class="divide-y divide-line text-sm">
                    @forelse ($history as $item)
                        <li class="px-5 py-3">
                            <div class="flex items-center justify-between gap-3">
                                @php $switch = in_array($item['action'] ?? null, ['disable', 'enable'], true); @endphp
                                @if (($item['action'] ?? null) === 'core')<x-common.badge color="brand">{{ __('updates.core_title') }}</x-common.badge>@endif
                                <span class="font-medium text-ink">{{ $item['module'] }} <span class="font-mono text-muted">{{ $switch ? '' : ($item['from_version'] ? $item['from_version'].' → ' : '').$item['to_version'] }}</span></span>
                                <x-common.badge :color="$item['status'] === 'success' ? ($item['action'] === 'disable' ? 'warning' : 'success') : 'danger'">{{ __('updates.result_'.($switch && $item['status'] === 'success' ? $item['action'] : $item['status'])) }}</x-common.badge>
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

        <div class="bg-card border border-line rounded-xl p-5 text-sm space-y-3">
            <div>
                <p class="text-muted">{{ __('updates.mode') }}</p>
                <p class="font-medium text-ink">{{ __('updates.mode_'.($mode === 'auto' ? 'auto' : 'manual')) }}</p>
                <p class="text-xs text-muted">{{ __('updates.mode_hint') }}</p>
            </div>
            @if ($support)
                <div>
                    <p class="text-muted">{{ __('updates.support') }}</p>
                    <p class="font-medium {{ $support['active'] ? 'text-ink' : 'text-danger' }}">
                        {{ $support['until'] ? \Carbon\Carbon::parse($support['until'])->format('d M Y') : __('updates.support_no_end') }}
                    </p>
                </div>
            @endif
            <div>
                <p class="text-muted">{{ __('updates.last_check') }}</p>
                <p class="font-medium text-ink">{{ $lastCheck ? \Carbon\Carbon::parse($lastCheck)->diffForHumans() : __('updates.never') }}</p>
            </div>
            <div>
                <p class="text-muted">{{ __('updates.version') }}</p>
                <p class="font-mono text-ink">{{ $version }}</p>
            </div>
            <form action="{{ route('superadmin.updates.link') }}" method="POST" class="pt-3 border-t border-line space-y-2">
                @csrf
                <p class="font-medium text-ink">{{ __('updates.link_title') }}</p>
                <p class="text-xs text-muted">{{ __('updates.link_hint') }}</p>
                <x-common.textarea name="key" :rows="3" :placeholder="'ISH1-…'" required />
                <x-common.button type="submit" size="sm" variant="secondary">{{ __('updates.link_button') }}</x-common.button>
            </form>
        </div>
    </div>
</x-layouts.app>
