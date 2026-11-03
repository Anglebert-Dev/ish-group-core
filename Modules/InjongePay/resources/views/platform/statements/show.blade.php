@php use Modules\InjongePay\Support\Money; $s = $check->summary; $u = $check->unmatched; @endphp
<x-layouts.app :title="__('injongepay::injongepay.statements')">
    <x-common.page-header :title="$check->period_from->format('d M Y').' – '.$check->period_to->format('d M Y')" :description="$check->file_name">
        <x-slot:actions>
            <x-common.button href="{{ route('injongepay.platform.statements.index') }}" variant="secondary" size="sm">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="rounded-xl border p-4 mb-6 {{ $check->agrees ? 'border-success/40 bg-success/5 text-success' : 'border-danger/40 bg-danger/5 text-danger' }} font-semibold">
        {{ $check->agrees ? __('injongepay::injongepay.statement_agrees') : __('injongepay::injongepay.statement_differs') }}
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @foreach ([
            [__('injongepay::injongepay.money_in'), $s['bank_credits'], $s['bank_credit_lines'], $s['our_payments'], $s['our_payment_count']],
            [__('injongepay::injongepay.money_out'), $s['bank_debits'], $s['bank_debit_lines'], $s['our_cashouts'], $s['our_cashout_count']],
        ] as [$label, $bank, $bankCount, $ours, $oursCount])
            <div class="bg-card border border-line rounded-xl p-5 space-y-1 text-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $label }}</p>
                <p>GTBank: <span class="font-semibold text-ink">{{ Money::format($bank) }}</span> ({{ $bankCount }})</p>
                <p>{{ __('injongepay::injongepay.ours') }}: <span class="font-semibold text-ink">{{ Money::format($ours) }}</span> ({{ $oursCount }})</p>
            </div>
        @endforeach
        <div class="bg-card border border-line rounded-xl p-5 space-y-1 text-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('injongepay::injongepay.closing_balance') }}</p>
            <p>GTBank: <span class="font-semibold text-ink">{{ $s['bank_closing'] !== null ? Money::format($s['bank_closing']) : '—' }}</span></p>
            <p>{{ __('injongepay::injongepay.books_hold') }}: <span class="font-semibold text-ink">{{ Money::format($s['books_hold']) }}</span></p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @foreach (['bank_credits' => 'unmatched_bank_credits', 'our_payments' => 'unmatched_our_payments', 'bank_debits' => 'unmatched_bank_debits', 'our_cashouts' => 'unmatched_our_cashouts'] as $key => $label)
            <div class="bg-card border border-line rounded-xl overflow-hidden">
                <h2 class="font-semibold text-ink px-5 pt-4">{{ __('injongepay::injongepay.'.$label) }} ({{ count($u[$key] ?? []) }})</h2>
                <table class="w-full text-sm mt-2"><tbody class="divide-y divide-line">
                    @forelse ($u[$key] ?? [] as $row)
                        <tr>
                            <td class="px-5 py-2 text-muted whitespace-nowrap">{{ $row['date'] ?? '—' }}</td>
                            <td class="px-5 py-2">{{ $row['text'] ?? $row['reference'] ?? '—' }}</td>
                            <td class="px-5 py-2 text-right font-semibold">{{ Money::format((int) ($row['credit'] ?? 0) ?: (int) ($row['debit'] ?? 0) ?: (int) ($row['amount'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-4 text-success">{{ __('injongepay::injongepay.all_matched') }}</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        @endforeach
    </div>
    <p class="text-xs text-muted mt-4">{{ __('injongepay::injongepay.columns_read', ['columns' => collect($s['columns'])->map(fn ($v, $k) => $k.': '.$v)->implode(' · ')]) }}</p>
</x-layouts.app>
