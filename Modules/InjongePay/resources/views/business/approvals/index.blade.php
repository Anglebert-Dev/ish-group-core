@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; use App\Helpers\AuthHelper; @endphp
<x-layouts.app :title="__('injongepay::injongepay.approvals')">
    <x-common.page-header :title="__('injongepay::injongepay.approvals')" :description="__('injongepay::injongepay.approvals_description')" />

    <div class="space-y-4">
        @forelse ($approvals as $approval)
            <div class="bg-card border border-line rounded-xl p-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="text-sm space-y-1">
                    <p class="text-xl font-display font-bold text-ink">{{ Money::format($approval->amount) }}</p>
                    <p>{{ __('injongepay::injongepay.operation_'.$approval->operation) }} · {{ $approval->wallet?->label() }}</p>
                    @if ($approval->operation === 'transfer')<p class="text-muted">→ {{ $approval->payload['to_merchant_code'] ?? '' }}</p>@endif
                    @if ($approval->operation === 'cashout')<p class="text-muted">→ {{ $approval->payload['account_label'] ?? '' }}</p>@endif
                    <p class="text-muted">{{ $approval->requester?->name }} · {{ GeneralLibrary::formatDateTime($approval->created_at) }}</p>
                    <x-common.badge :color="['awaiting_approval' => 'warning', 'executed' => 'success', 'rejected' => 'danger', 'failed' => 'danger', 'expired' => 'muted', 'approved' => 'brand'][$approval->status] ?? 'muted'">{{ __('injongepay::injongepay.approval_'.$approval->status) }}</x-common.badge>
                    @if ($approval->rejection_reason || $approval->failure_reason)<p class="text-danger">{{ $approval->rejection_reason ?? $approval->failure_reason }}</p>@endif
                </div>
                @if ($approval->status === 'awaiting_approval' && AuthHelper::can('approve injongepay.'.$approval->operation) && $approval->requested_by !== auth('staff')->id())
                    <form action="{{ route('injongepay.approvals.approve', $approval) }}" method="POST" class="space-y-2">
                        @csrf
                        <x-common.input name="pin" :id="'pin-'.$approval->id" type="password" inputmode="numeric" maxlength="5" autocomplete="off" :label="__('injongepay::injongepay.your_pin')" required compact />
                        <x-common.button type="submit" size="sm">{{ __('injongepay::injongepay.approve') }}</x-common.button>
                    </form>
                    <form action="{{ route('injongepay.approvals.reject', $approval) }}" method="POST" class="space-y-2">
                        @csrf
                        <x-common.input name="reason" :id="'reason-'.$approval->id" :label="__('injongepay::injongepay.reason')" required compact />
                        <x-common.button type="submit" size="sm" variant="secondary">{{ __('injongepay::injongepay.reject') }}</x-common.button>
                    </form>
                @endif
            </div>
        @empty
            <x-common.empty-state :title="__('injongepay::injongepay.no_approvals')" />
        @endforelse
    </div>
    <x-common.pagination :paginator="$approvals" class="mt-4" />
</x-layouts.app>
