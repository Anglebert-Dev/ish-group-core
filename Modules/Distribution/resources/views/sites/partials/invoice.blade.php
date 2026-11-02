{{-- One invoice: what it is, where it stands, and what can be done next. --}}
@php
    $months = \Modules\Distribution\Services\LicenceService::monthsOf($charge);
    $panel = 'rounded-lg border border-line bg-surface/60 p-4 mt-3 space-y-3 max-w-md';
@endphp
<div class="rounded-xl border border-line p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-semibold text-ink">{{ __('distribution::distribution.invoice_open_title', ['number' => $charge->number]) }}
                <x-common.badge :color="$charge->status->color()" class="ml-1">{{ $charge->status->label() }}</x-common.badge>
                @if ($charge->isOpen() && $charge->due_on?->isPast())<x-common.badge color="danger">{{ __('distribution::distribution.charge_overdue') }}</x-common.badge>@endif
            </p>
            <p class="text-sm text-body mt-1">{{ $charge->description }}</p>
            <p class="text-xs text-muted mt-1">
                {{ __('distribution::distribution.invoice_issued', ['date' => $charge->issued_on->format('d M Y')]) }}
                @if ($charge->isOpen() && $charge->due_on) · {{ __('distribution::distribution.invoice_due', ['date' => $charge->due_on->format('d M Y')]) }}@endif
                · {{ $months ? __('distribution::distribution.invoice_includes', ['months' => $months]) : __('distribution::distribution.invoice_includes_none') }}
                @if ($charge->isOpen()) · {{ $charge->emailed_at ? __('distribution::distribution.invoice_sent', ['when' => $charge->emailed_at->diffForHumans()]) : __('distribution::distribution.invoice_not_sent') }}@endif
            </p>
            @if ($charge->paid_on)
                <p class="text-xs text-success mt-1">{{ __('distribution::distribution.charge_paid_meta', ['date' => $charge->paid_on->format('d M Y'), 'method' => $charge->payment_method ? __('distribution::distribution.method_'.$charge->payment_method) : '—', 'reference' => $charge->payment_reference ?: '—']) }}</p>
            @endif
        </div>
        <p class="text-lg font-semibold text-ink whitespace-nowrap">{{ money($charge->amount) }}</p>
    </div>

    <div class="flex flex-wrap gap-4 mt-3 text-xs font-semibold">
        <a href="{{ route('print.show', ['distribution_charge', $charge->getRouteKey()]) }}" target="_blank" rel="noopener" class="text-link hover:underline">{{ __('body.print') }}</a>
        <a href="{{ route('print.pdf', ['distribution_charge', $charge->getRouteKey()]) }}" class="text-link hover:underline">PDF</a>
    </div>

    @if ($charge->isOpen())
        <div class="flex flex-wrap gap-2 mt-3" data-invoice-actions>
            @foreach (['send' => __('distribution::distribution.invoice_send'), 'edit' => __('distribution::distribution.charge_edit'), 'pay' => __('distribution::distribution.charge_mark_paid'), 'cancel' => __('distribution::distribution.charge_cancel')] as $action => $label)
                <button type="button" data-invoice-open="{{ $charge->id }}-{{ $action }}"
                    class="h-8 px-3 rounded-lg border text-xs font-semibold {{ $action === 'pay' ? 'border-success text-success' : ($action === 'cancel' ? 'border-line text-danger' : 'border-line text-ink') }} hover:bg-surface">{{ $label }}</button>
            @endforeach
        </div>

        <form id="invoice-{{ $charge->id }}-send" action="{{ route('superadmin.distribution.sites.charges.email', [$site, $charge]) }}" method="POST" class="hidden {{ $panel }}">
            @csrf
            <x-common.input name="email" id="inv{{ $charge->id }}-email" type="email" :label="__('distribution::distribution.contact_email')" :value="$site->contact_email" required />
            <x-common.button type="submit" size="sm">{{ __('distribution::distribution.invoice_send') }}</x-common.button>
        </form>

        <form id="invoice-{{ $charge->id }}-edit" action="{{ route('superadmin.distribution.sites.charges.update', [$site, $charge]) }}" method="POST" class="hidden {{ $panel }}">
            @csrf
            @method('PUT')
            <x-common.input name="amount" id="inv{{ $charge->id }}-amount" type="number" step="0.01" min="0" :label="__('distribution::distribution.charge_amount')" :value="$charge->amount" required />
            <x-common.input name="support_months" id="inv{{ $charge->id }}-support_months" type="number" min="0" max="60" :label="__('distribution::distribution.charge_support_months')" :value="$months" :hint="__('distribution::distribution.charge_support_months_hint')" />
            <x-common.input name="due_on" id="inv{{ $charge->id }}-due_on" type="date" :label="__('distribution::distribution.change_due_on')" :value="$charge->due_on?->toDateString()" />
            <x-common.input name="description" id="inv{{ $charge->id }}-description" :label="__('distribution::distribution.charge_description')" :value="$charge->description" :hint="__('distribution::distribution.charge_description_hint')" />
            <x-common.button type="submit" size="sm">{{ __('distribution::distribution.charge_save') }}</x-common.button>
        </form>

        <form id="invoice-{{ $charge->id }}-pay" action="{{ route('superadmin.distribution.sites.charges.pay', [$site, $charge]) }}" method="POST" class="hidden {{ $panel }}">
            @csrf
            <x-common.input name="paid_on" id="inv{{ $charge->id }}-paid_on" type="date" :label="__('distribution::distribution.charge_paid_on')" :value="today()->toDateString()" required />
            <x-common.select name="payment_method" id="inv{{ $charge->id }}-payment_method" :label="__('distribution::distribution.charge_method')" :options="collect(\Modules\Distribution\Models\Charge::METHODS)->mapWithKeys(fn ($m) => [$m => __('distribution::distribution.method_'.$m)])->all()" />
            <x-common.input name="payment_reference" id="inv{{ $charge->id }}-payment_reference" :label="__('distribution::distribution.charge_reference')" />
            <x-common.button type="submit" size="sm">{{ __('distribution::distribution.charge_mark_paid') }}</x-common.button>
        </form>

        <form id="invoice-{{ $charge->id }}-cancel" action="{{ route('superadmin.distribution.sites.charges.cancel', [$site, $charge]) }}" method="POST" class="hidden {{ $panel }}">
            @csrf
            <p class="text-xs text-muted">{{ __('distribution::distribution.invoice_cancel_hint') }}</p>
            <x-common.input name="reason" id="inv{{ $charge->id }}-reason" :label="__('distribution::distribution.charge_cancel_reason')" required />
            <x-common.button type="submit" variant="danger" size="sm">{{ __('distribution::distribution.charge_cancel') }}</x-common.button>
        </form>
    @endif
</div>

@once
<script>
// One action form open at a time per invoice; clicking again closes it.
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-invoice-open]');
    if (!btn) return;
    var id = btn.getAttribute('data-invoice-open'), invoice = id.split('-')[0];
    document.querySelectorAll('form[id^="invoice-' + invoice + '-"]').forEach(function (form) {
        form.classList.toggle('hidden', form.id !== 'invoice-' + id || !form.classList.contains('hidden'));
    });
});
</script>
@endonce
