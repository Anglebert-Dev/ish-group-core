<x-layouts.app :title="__('garmentpack::garment.cmt')">
    <x-common.page-header :title="__('garmentpack::garment.cmt')" :description="__('garmentpack::garment.cmt_description')" />

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                <th class="px-5 py-3 font-semibold">#</th>
                <th class="px-5 py-3 font-semibold">{{ __('garmentpack::garment.subcontractor') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('production::production.work_order') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('garmentpack::garment.stage') }}</th>
                <th class="px-5 py-3 font-semibold text-right">{{ __('garmentpack::garment.sent') }}</th>
                <th class="px-5 py-3 font-semibold text-right">{{ __('garmentpack::garment.returned') }}</th>
                <th class="px-5 py-3 font-semibold text-right">{{ __('garmentpack::garment.owed') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($jobs as $job)
                    <tr>
                        <td class="px-5 py-3 font-medium">{{ $job->number }}</td>
                        <td class="px-5 py-3">{{ $job->subcontractor }}<span class="block text-xs text-muted">{{ $job->phone }}</span></td>
                        <td class="px-5 py-3">@if ($job->workOrder)<a href="{{ route('production.work-orders.show', $job->workOrder) }}" class="text-brand font-semibold">{{ $job->workOrder->number }}</a>@endif</td>
                        <td class="px-5 py-3">{{ $job->stage_name }}</td>
                        <td class="px-5 py-3 text-right">@include('inventory::partials.qty', ['value' => $job->sent_quantity])</td>
                        <td class="px-5 py-3 text-right">@include('inventory::partials.qty', ['value' => $job->returned_quantity])</td>
                        <td class="px-5 py-3 text-right">{{ money($job->owed()) }}</td>
                        <td class="px-5 py-3"><x-common.badge :color="$job->status === 'returned' ? 'success' : 'warning'">{{ __('garmentpack::garment.cmt_status_'.$job->status) }}</x-common.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-10 text-center text-muted">{{ __('garmentpack::garment.cmt_none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$jobs" />
    </div>
</x-layouts.app>
