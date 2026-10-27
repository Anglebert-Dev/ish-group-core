<x-layouts.app title="{{ __('body.audit_logs') }}">
    <x-common.page-header :title="__('body.audit_logs')" :description="__('body.audit_logs_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('activity-logs.export.pdf', request()->query())"
                :excel="route('activity-logs.export.excel', request()->query())"
            />
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.when') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.actor') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.action') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.subject') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($logs as $log)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">@kigali($log->created_at)</td>
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">{{ $log->actorName() }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$log->action->label()" /></td>
                        <td class="px-5 py-3.5 text-muted">
                            <a href="{{ route('activity-logs.show', $log) }}" class="hover:text-brand">{{ $log->description ?: $log->subjectName() }}</a>
                        </td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('activity-logs.show', $log) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('body.no_audit_logs_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$logs" />
    </div>
</x-layouts.app>
