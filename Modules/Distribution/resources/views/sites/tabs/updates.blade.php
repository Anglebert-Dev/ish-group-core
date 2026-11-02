<div class="p-5 space-y-2 text-sm border-b border-line">
            <p class="font-semibold text-ink mb-1">{{ __('distribution::distribution.reported') }}</p>
            <div class="grid sm:grid-cols-2 gap-x-8 gap-y-2">
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.last_check_in') }}</span><span>{{ $site->last_check_in_at?->diffForHumans() ?? __('distribution::distribution.never') }}</span></div>
                @php $latestCore = \Modules\Distribution\Models\Release::latestFor([\Modules\Distribution\Models\Release::CORE], $site->is_test)[\Modules\Distribution\Models\Release::CORE] ?? null; @endphp
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.core_version') }}</span>
                    <span>{{ $site->core_version ?: '—' }}
                        @if ($latestCore && version_compare($latestCore->version, \Modules\Distribution\Models\Release::plainVersion($site->core_version), '>'))
                            <span class="text-warning">→ {{ $latestCore->version }} {{ __('distribution::distribution.core_available') }}</span>
                        @endif
                    </span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.php_version') }}</span><span>{{ $site->php_version ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.db_driver') }}</span><span>{{ $site->db_driver ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.modules_writable') }}</span>
                    <span>{{ $site->modules_writable === null ? '—' : ($site->modules_writable ? __('distribution::distribution.yes') : __('distribution::distribution.no')) }}</span></div>
            </div>
</div>
<div class="grid gap-6 lg:grid-cols-2 p-5">
        <div class="bg-card border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-line"><p class="font-semibold text-ink">{{ __('distribution::distribution.update_history') }}</p></div>
            <ul class="divide-y divide-line text-sm">
                @forelse ($site->updateReports as $report)
                    <li class="px-5 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium text-ink">{{ $report->module }} {{ in_array($report->action, ['disable', 'enable'], true) ? __('distribution::distribution.action_'.$report->action) : ($report->from_version ? $report->from_version.' → ' : '').$report->to_version }}</span>
                            <x-common.badge :color="$report->status === 'success' ? 'success' : 'danger'">{{ __('distribution::distribution.report_'.$report->status) }}</x-common.badge>
                        </div>
                        <div class="text-xs text-muted mt-0.5">{{ $report->created_at?->format('d M Y H:i') }}</div>
                        @if ($report->error)<div class="text-xs text-danger mt-1 break-words">{{ $report->error }}</div>@endif
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-muted">{{ __('distribution::distribution.no_update_history') }}</li>
                @endforelse
            </ul>
        </div>
        <div class="bg-card border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-line"><p class="font-semibold text-ink">{{ __('distribution::distribution.recent_check_ins') }}</p></div>
            <ul class="divide-y divide-line text-sm">
                @forelse ($site->checkIns as $checkIn)
                    <li class="px-5 py-3 flex items-center justify-between gap-3">
                        <span class="text-muted whitespace-nowrap">{{ $checkIn->created_at?->format('d M Y H:i') }}</span>
                        <span class="text-right">{{ $checkIn->offered ? __('distribution::distribution.offered').': '.implode(', ', $checkIn->offered) : __('distribution::distribution.nothing_offered') }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-muted">{{ __('distribution::distribution.no_check_ins') }}</li>
                @endforelse
            </ul>
        </div>
</div>
