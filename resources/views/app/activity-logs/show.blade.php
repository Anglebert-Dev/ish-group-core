<x-layouts.app title="{{ $log->description ?: __('body.audit_log_details') }}">
    <div data-details-page>
        <x-common.details.header
            :title="$log->description ?: __('body.audit_log_details')"
            :subtitle="__('body.audit_log_details')"
            :back-href="route('activity-logs.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$log->action->label()" />
            </x-slot:meta>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.actor')" :value="$log->actorName()" />
            <x-common.details.summary-item :label="__('body.subject')" :value="$log->subjectName()" />
            <x-common.details.summary-item :label="__('body.action')">
                <x-common.status :status="$log->action->label()" />
            </x-common.details.summary-item>
            <x-common.details.summary-item :label="__('body.when')">
                @kigali($log->created_at)
            </x-common.details.summary-item>
        </x-common.details.summary>

        <div class="space-y-6">
            <x-common.details.section
                :title="__('body.overview')"
                icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
            >
                <x-common.details.grid>
                    <x-common.details.field :label="__('body.when')">@kigali($log->created_at)</x-common.details.field>
                    <x-common.details.field :label="__('body.actor')">{{ $log->actorName() }}</x-common.details.field>
                    <x-common.details.field :label="__('body.action')">
                        <x-common.status :status="$log->action->label()" />
                    </x-common.details.field>
                    <x-common.details.field :label="__('body.subject')">{{ $log->subjectName() }}</x-common.details.field>
                    <x-common.details.field :label="__('body.ip_address')">{{ $log->ip_address ?: '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.user_agent')">
                        <span class="break-all text-sm">{{ $log->user_agent ?: '—' }}</span>
                    </x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>

            @php
                $attributes = $log->properties['attributes'] ?? [];
                $old = $log->properties['old'] ?? [];
                $keys = collect(array_unique(array_merge(array_keys($old), array_keys($attributes))))->sort()->values();
            @endphp

            <x-common.details.section
                :title="__('body.changes')"
                icon="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                tone="ink"
            >
                @if ($keys->isEmpty())
                    <p class="text-sm text-muted">{{ __('body.no_changes_recorded') }}</p>
                @else
                    <div class="overflow-x-auto -mx-1">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                                    <th class="px-3 py-3 font-semibold">{{ __('body.details') }}</th>
                                    @if ($log->action->value === 'updated')
                                        <th class="px-3 py-3 font-semibold">{{ __('body.old_value') }}</th>
                                    @endif
                                    <th class="px-3 py-3 font-semibold">{{ __('body.new_value') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($keys as $key)
                                    <tr>
                                        <td class="px-3 py-3 font-medium text-ink whitespace-nowrap">{{ str_replace('_', ' ', $key) }}</td>
                                        @if ($log->action->value === 'updated')
                                            <td class="px-3 py-3 text-muted break-all">{{ is_scalar($old[$key] ?? null) || is_null($old[$key] ?? null) ? ($old[$key] ?? '—') : json_encode($old[$key]) }}</td>
                                        @endif
                                        <td class="px-3 py-3 text-muted break-all">{{ is_scalar($attributes[$key] ?? null) || is_null($attributes[$key] ?? null) ? ($attributes[$key] ?? '—') : json_encode($attributes[$key]) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-common.details.section>
        </div>
    </div>
</x-layouts.app>
