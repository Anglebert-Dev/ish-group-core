<x-layouts.app :title="__('body.dashboard')">

    <x-common.page-header :title="__('body.dashboard')" :description="__('body.dashboard_description')" />

    @php
        $columns = ['2' => 'lg:grid-cols-2', '3' => 'lg:grid-cols-3', '4' => 'lg:grid-cols-4'][(string) ($dashboard['stat_columns'] ?? 4)];
        $banner = $dashboard['banner'] ?? 'none';
        $cover = $banner === 'image' ? app(\App\Services\Branding\BrandingService::class)->loginPage($branding)['image'] : null;
    @endphp
    <div class="space-y-6">
        @foreach ($blocks as $block)
            @if ($block === 'welcome')
                @if (! empty($dashboard['welcome_title']) || ! empty($dashboard['welcome_text']) || $banner !== 'none')
                    <div class="bg-card border border-line rounded-xl overflow-hidden">
                        @if ($cover)
                            <div class="h-28 sm:h-36 bg-cover bg-center" style="background-image: url('{{ $cover }}')"></div>
                        @endif
                        <div class="p-5 flex items-center gap-4">
                            @if ($banner === 'logo')
                                <x-common.logo :href="route('dashboard')" />
                            @endif
                            <div class="min-w-0">
                                @if (! empty($dashboard['welcome_title']))
                                    <p class="font-display font-bold text-lg text-ink">{{ str_replace(':name', auth('staff')->user()->name, $dashboard['welcome_title']) }}</p>
                                @endif
                                @if (! empty($dashboard['welcome_text']))
                                    <p class="text-sm text-body mt-1">{!! nl2br(e($dashboard['welcome_text'])) !!}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            @elseif ($block === 'stats' && $stats->isNotEmpty())
                <div class="grid grid-cols-2 {{ $columns }} gap-3 sm:gap-4">
                    @foreach ($stats as $stat)
                        <x-dashboard.stat-card
                            :label="$stat['label']"
                            :value="$stat['value']"
                            :href="$stat['href'] ?? null"
                            :hint="$stat['hint'] ?? null"
                        />
                    @endforeach
                </div>
            @elseif ($block === 'quick_actions')
                <x-dashboard.quick-actions :actions="$quickActions" />
            @elseif ($block === 'recent_activity' && $recentActivity->isNotEmpty())
                <div class="bg-card border border-line rounded-xl overflow-hidden">
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
            @elseif (isset($widgets[$block]))
                {!! $widgets[$block] !!}
            @endif
        @endforeach
    </div>

</x-layouts.app>
