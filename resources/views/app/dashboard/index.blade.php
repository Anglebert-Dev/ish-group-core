<x-layouts.app :title="__('body.dashboard')">

    <x-common.page-header :title="__('body.dashboard')" :description="__('body.dashboard_description')" />

    <div class="space-y-6">
        @if ($stats->isNotEmpty())
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                @foreach ($stats as $stat)
                    <x-dashboard.stat-card
                        :label="$stat['label']"
                        :value="$stat['value']"
                        :href="$stat['href'] ?? null"
                        :hint="$stat['hint'] ?? null"
                    />
                @endforeach
            </div>
        @endif

        <x-dashboard.quick-actions :actions="$quickActions" />

        @foreach ($widgets as $widget)
            {!! $widget !!}
        @endforeach

        @if ($recentActivity->isNotEmpty())
            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-line">
                    <h2 class="text-base font-semibold text-ink">{{ __('body.recent_activity') }}</h2>
                    <a href="{{ route('activity-logs.index') }}" class="text-sm font-medium text-brand hover:text-brand-dark">{{ __('body.view_all') }}</a>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($recentActivity as $log)
                        <li class="px-5 py-3 flex items-center justify-between gap-4 text-sm">
                            <div class="min-w-0">
                                <p class="text-ink truncate">
                                    <span class="font-medium">{{ $log->actorName() }}</span>
                                    <span class="text-muted">·</span>
                                    <a href="{{ route('activity-logs.show', $log) }}" class="hover:text-brand">{{ $log->description ?: $log->subjectName() }}</a>
                                </p>
                            </div>
                            <span class="shrink-0 text-xs text-muted">@datetime($log->created_at)</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

</x-layouts.app>
