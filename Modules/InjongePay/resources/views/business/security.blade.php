<x-layouts.app :title="__('injongepay::injongepay.security')">
    <x-common.page-header :title="__('injongepay::injongepay.security')" :description="__('injongepay::injongepay.security_description')" />
    @include('injongepay::partials.test-mode')

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
        <form action="{{ route('injongepay.security.pin') }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
            @csrf
            <h2 class="font-display font-bold text-ink">{{ $hasPin ? __('injongepay::injongepay.pin_change') : __('injongepay::injongepay.pin_set') }}</h2>
            <p class="text-sm text-muted">{{ __('injongepay::injongepay.pin_hint') }}</p>
            <x-common.input name="pin" type="password" inputmode="numeric" maxlength="5" autocomplete="new-password" :label="__('injongepay::injongepay.new_pin')" required />
            <x-common.input name="pin_confirmation" type="password" inputmode="numeric" maxlength="5" autocomplete="new-password" :label="__('injongepay::injongepay.new_pin_again')" required />
            @if ($hasPin)
                @if (session('pin_step_up'))
                    <x-common.input name="code" inputmode="numeric" maxlength="8" autocomplete="one-time-code" :label="__('injongepay::injongepay.step_up_code')" :hint="__('injongepay::injongepay.step_up_needed_'.session('pin_step_up'))" required autofocus />
                @else
                    <p class="text-xs text-muted">{{ __('injongepay::injongepay.pin_change_needs_code_'.$method) }}</p>
                @endif
            @endif
            <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
        </form>

        <div class="bg-card border border-line rounded-xl p-5 space-y-3">
            <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.devices') }}</h2>
            <p class="text-sm text-muted">{{ __('injongepay::injongepay.devices_hint_'.$method) }}</p>
            @forelse ($devices as $device)
                <div class="flex items-center gap-3 border-b border-line pb-2 text-sm">
                    <div class="flex-1 min-w-0">
                        <p class="truncate text-ink">{{ $device->label }}</p>
                        <p class="text-xs text-muted">{{ $device->ip }} · {{ \App\Libraries\GeneralLibrary::formatDateTime($device->last_used_at) }}</p>
                    </div>
                    <x-common.action-button :action="route('injongepay.security.devices.revoke', $device)" method="DELETE" color="danger">{{ __('injongepay::injongepay.revoke') }}</x-common.action-button>
                </div>
            @empty
                <p class="text-sm text-muted">{{ __('injongepay::injongepay.no_devices') }}</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
