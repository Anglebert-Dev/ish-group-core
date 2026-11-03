<div class="bg-card border border-line rounded-xl p-5 space-y-3">
    <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.history') }}</h2>
    @foreach ($cashout->events as $event)
        <div class="text-sm border-l-2 border-line pl-3">
            <p class="text-ink"><span class="font-semibold">{{ __('injongepay::injongepay.cashout_'.$event->to_status) }}</span> · {{ __('injongepay::injongepay.event_source_'.$event->source) }}</p>
            @if ($event->note)<p class="text-muted">{{ $event->note }}</p>@endif
            <p class="text-xs text-muted">{{ \App\Libraries\GeneralLibrary::formatDateTime($event->created_at, 'd M Y H:i:s') }} · {{ $event->actor_name }}</p>
        </div>
    @endforeach
</div>
