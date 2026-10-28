@php $cell = 'w-28 rounded-lg border border-line bg-white px-2.5 py-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand'; @endphp
<x-layouts.app :title="__('delivery::delivery.add_title', ['number' => $order->number])">
    <x-common.page-header :title="__('delivery::delivery.add_title', ['number' => $order->number])" :description="$order->customer?->displayName().' · '.__('delivery::delivery.from_store', ['store' => $order->store?->name])">
        <x-slot:actions>
            <x-common.button href="{{ route('salesorders.orders.show', $order) }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form action="{{ route('delivery.notes.store', $order) }}" method="POST" class="space-y-6 max-w-5xl" data-delivery-form>
        @csrf
        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted">{{ __('delivery::delivery.add_hint') }}</p>
                <button type="button" data-fill-all class="text-sm font-semibold text-brand">{{ __('delivery::delivery.deliver_all') }}</button>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        <th class="px-3 py-2.5 font-semibold">{{ __('inventory::inventory.line_item') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('delivery::delivery.ordered') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('delivery::delivery.delivered_before') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('delivery::delivery.remaining') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('delivery::delivery.now') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($order->lines as $line)
                        <tr>
                            <td class="px-3 py-3 font-medium text-ink">{{ $line->variant?->displayName() }}</td>
                            <td class="px-3 py-3 text-right">@include('inventory::partials.qty', ['value' => $line->quantity])</td>
                            <td class="px-3 py-3 text-right text-muted">@include('inventory::partials.qty', ['value' => $line->delivered_quantity])</td>
                            <td class="px-3 py-3 text-right font-semibold">@include('inventory::partials.qty', ['value' => $line->remaining()])</td>
                            <td class="px-3 py-3 text-right">
                                <input type="number" step="any" min="0" max="{{ $line->remaining() }}" name="quantities[{{ $line->id }}]" value="{{ old('quantities.'.$line->id) }}"
                                    data-remaining="{{ $line->remaining() }}" class="{{ $cell }}" @disabled($line->remaining() <= 0)>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-common.input name="delivered_on" type="date" :label="__('delivery::delivery.delivered_on')" :value="old('delivered_on', now()->toDateString())" required />
                <x-common.input name="receiver_name" :label="__('delivery::delivery.receiver_name')" :value="old('receiver_name', $order->customer?->contact_person)" required />
                <x-common.input name="receiver_phone" :label="__('delivery::delivery.receiver_phone')" :value="old('receiver_phone', $order->customer?->phone)" />
                <x-common.input name="vehicle" :label="__('delivery::delivery.vehicle')" :value="old('vehicle')" />
            </div>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-ink">{{ __('delivery::delivery.signature') }}</p>
                    <button type="button" data-signature-clear class="text-xs font-semibold text-danger">{{ __('delivery::delivery.signature_clear') }}</button>
                </div>
                <canvas data-signature width="600" height="180" class="w-full max-w-xl h-44 rounded-xl border border-dashed border-line bg-surface touch-none"></canvas>
                <p class="text-xs text-muted">{{ __('delivery::delivery.signature_hint') }}</p>
                <input type="hidden" name="signature" data-signature-input>
            </div>
            <x-common.textarea name="notes" :label="__('delivery::delivery.notes_label')" :value="old('notes')" :rows="2" />
        </div>

        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>

    @push('scripts')
        <script>
            (function () {
                var form = document.querySelector('[data-delivery-form]');
                if (!form) return;
                form.querySelector('[data-fill-all]').addEventListener('click', function () {
                    form.querySelectorAll('[data-remaining]').forEach(function (input) { if (!input.disabled) input.value = input.dataset.remaining; });
                });

                var canvas = form.querySelector('[data-signature]');
                var ctx = canvas.getContext('2d');
                var drawing = false, signed = false;
                ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.strokeStyle = '#111827';
                function point(e) {
                    var r = canvas.getBoundingClientRect();
                    return { x: (e.clientX - r.left) * canvas.width / r.width, y: (e.clientY - r.top) * canvas.height / r.height };
                }
                canvas.addEventListener('pointerdown', function (e) { drawing = true; var p = point(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); canvas.setPointerCapture(e.pointerId); });
                canvas.addEventListener('pointermove', function (e) { if (!drawing) return; var p = point(e); ctx.lineTo(p.x, p.y); ctx.stroke(); signed = true; });
                canvas.addEventListener('pointerup', function () { drawing = false; });
                form.querySelector('[data-signature-clear]').addEventListener('click', function () { ctx.clearRect(0, 0, canvas.width, canvas.height); signed = false; });
                form.addEventListener('submit', function () { form.querySelector('[data-signature-input]').value = signed ? canvas.toDataURL('image/png') : ''; });
            })();
        </script>
    @endpush
</x-layouts.app>
