{{-- Business audit (activity-logs.*) and platform audit (superadmin.audit.*) share this view. --}}
@php
    $exportPdf = $allBusinesses ? route($exportRoute, ['pdf'] + request()->query()) : route('activity-logs.export.pdf', request()->query());
    $exportExcel = $allBusinesses ? route($exportRoute, ['excel'] + request()->query()) : route('activity-logs.export.excel', request()->query());
@endphp

<x-layouts.app title="{{ __('body.audit_logs') }}">
    @if ($allBusinesses)
        <x-superadmin.nav />
    @endif

    <x-common.page-header :title="__('body.audit_logs')" :description="$allBusinesses ? __('body.audit_platform_description') : __('body.audit_logs_description')">
        <x-slot:actions>
            <x-common.export-buttons :pdf="$exportPdf" :excel="$exportExcel" />
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route($indexRoute)" :open="array_filter($filters) !== []">
        <x-common.input name="q" :label="__('body.search')" :value="$filters['q'] ?? ''" :placeholder="__('body.audit_search_placeholder')" compact />
        <x-common.select name="action" :label="__('body.action')" :value="$filters['action'] ?? ''" compact hide-placeholder :options="['' => __('body.all')] + $actions" />
        @if ($allBusinesses)
            <x-common.select name="business" :label="__('body.business')" :value="$filters['business'] ?? ''" compact hide-placeholder :options="['' => __('body.all')] + $businesses" />
        @else
            <x-common.select name="user" :label="__('body.actor')" :value="$filters['user'] ?? ''" compact hide-placeholder :options="['' => __('body.all')] + $people->all()" />
        @endif
        <x-common.input name="from" type="date" :label="__('body.audit_from')" :value="$filters['from'] ?? ''" compact />
        <x-common.input name="to" type="date" :label="__('body.audit_to')" :value="$filters['to'] ?? ''" compact />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('body.when') }}</th>
                    @if ($allBusinesses)<th class="px-5 py-3 font-semibold">{{ __('body.business') }}</th>@endif
                    <th class="px-5 py-3 font-semibold">{{ __('body.actor') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.action') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.subject') }}</th>
                    <th class="px-5 py-3 font-semibold">IP</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($logs as $log)
                    <tr class="hover:bg-surface align-top">
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">@datetime($log->created_at)</td>
                        @if ($allBusinesses)<td class="px-5 py-3.5 whitespace-nowrap">{{ $log->business?->name ?? __('body.audit_platform') }}</td>@endif
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">{{ $log->actorName() }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap"><x-common.badge :color="$log->action->color()">{{ $log->action->label() }}</x-common.badge></td>
                        <td class="px-5 py-3.5 text-body min-w-[16rem]">{{ $log->description ?: $log->subjectName() }}</td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap font-mono text-xs">{{ $log->ip_address ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <x-common.action-button :href="route($showRoute, $allBusinesses ? $log->uuid : $log)">{{ __('body.view') }}</x-common.action-button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $allBusinesses ? 7 : 6 }}" class="px-5 py-10 text-center text-muted">{{ __('body.no_audit_logs_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$logs" class="border-t border-line" />
    </div>
</x-layouts.app>
