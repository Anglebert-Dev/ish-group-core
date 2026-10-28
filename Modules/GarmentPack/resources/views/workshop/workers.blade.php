@php $can = fn ($p) => \App\Helpers\AuthHelper::can($p); @endphp
<x-layouts.app :title="__('garmentpack::garment.workers')">
    <x-common.page-header :title="__('garmentpack::garment.workers')" :description="__('garmentpack::garment.workers_description')" />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <form method="GET" class="px-5 py-4 border-b border-line flex flex-wrap items-end gap-3">
                    <h2 class="font-display font-bold text-ink mr-auto">{{ __('garmentpack::garment.payroll') }}</h2>
                    <div class="w-40"><x-common.input name="from" type="date" :label="__('garmentpack::garment.from')" :value="$from" compact /></div>
                    <div class="w-40"><x-common.input name="to" type="date" :label="__('garmentpack::garment.to')" :value="$to" compact /></div>
                    <x-common.button type="submit" variant="secondary" size="sm">{{ __('garmentpack::garment.show') }}</x-common.button>
                </form>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                        <th class="px-5 py-3 font-semibold">{{ __('garmentpack::garment.worker') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('garmentpack::garment.pieces') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('garmentpack::garment.earned') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('garmentpack::garment.unpaid') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($payroll as $row)
                            <tr>
                                <td class="px-5 py-3 font-medium text-ink">{{ $row['worker']->name }}</td>
                                <td class="px-5 py-3 text-right">@include('inventory::partials.qty', ['value' => $row['pieces']])</td>
                                <td class="px-5 py-3 text-right">{{ money($row['amount']) }}</td>
                                <td class="px-5 py-3 text-right font-semibold {{ $row['unpaid'] > 0 ? 'text-danger' : 'text-success' }}">{{ money($row['unpaid']) }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if ($row['unpaid'] > 0 && $can('approve garments.worker'))
                                        <x-common.action-button :action="route('garmentpack.workers.paid', $row['worker'])" color="success">
                                            <x-slot:fields><input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"></x-slot:fields>
                                            {{ __('garmentpack::garment.mark_paid') }}
                                        </x-common.action-button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-muted">{{ __('garmentpack::garment.payroll_none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.workers') }}</h2></div>
                <div class="divide-y divide-line">
                    @forelse ($workers as $worker)
                        <details class="px-5 py-3">
                            <summary class="cursor-pointer flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-ink">{{ $worker->name }} <span class="text-muted font-normal">{{ $worker->line ? '· '.$worker->line : '' }} {{ $worker->phone ? '· '.$worker->phone : '' }}</span></span>
                                @unless ($worker->is_active)<x-common.badge color="muted">{{ __('inventory::inventory.status_inactive') }}</x-common.badge>@endunless
                            </summary>
                            @if ($can('update garments.worker'))
                                <form action="{{ route('garmentpack.workers.update', $worker) }}" method="POST" class="mt-3 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                                    @csrf @method('PUT')
                                    <x-common.input name="name" :id="'w-name-'.$worker->id" :label="__('body.name')" :value="$worker->name" required compact />
                                    <x-common.input name="phone" :id="'w-phone-'.$worker->id" :label="__('garmentpack::garment.phone')" :value="$worker->phone" compact />
                                    <x-common.input name="line" :id="'w-line-'.$worker->id" :label="__('garmentpack::garment.line')" :value="$worker->line" compact />
                                    <div class="flex items-center gap-3">
                                        <input type="hidden" name="is_active" value="0">
                                        <x-common.checkbox name="is_active" :id="'w-active-'.$worker->id" value="1" :label="__('inventory::inventory.status_active')" :checked="$worker->is_active" />
                                        <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                                    </div>
                                </form>
                            @endif
                        </details>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-muted">{{ __('garmentpack::garment.workers_none') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        @if ($can('create garments.worker'))
            <form action="{{ route('garmentpack.workers.store') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.worker_add') }}</h2>
                <x-common.input name="name" :label="__('body.name')" required />
                <x-common.input name="phone" :label="__('garmentpack::garment.phone')" />
                <x-common.input name="line" :label="__('garmentpack::garment.line')" />
                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
