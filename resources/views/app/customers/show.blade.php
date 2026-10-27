@php $canUpdate = \App\Helpers\AuthHelper::can('update customers.customer'); @endphp
<x-layouts.app :title="$customer->displayName()">
    <x-common.page-header :title="$customer->displayName()" :description="$customer->type?->label()">
        <x-slot:actions>
            <x-common.button href="{{ route('customers.index') }}" variant="secondary" size="sm">← {{ __('body.customers') }}</x-common.button>
            @if ($canUpdate)
                <x-common.button href="{{ route('customers.edit', $customer) }}" size="sm">{{ __('body.edit') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-common.info-card :title="__('body.customer_identity')" :rows="[
                    [__('body.customer_company_name'), $customer->company_name],
                    [__('body.customer_name'), $customer->name],
                    [__('body.customer_contact_person'), $customer->contact_person],
                    [__('body.customer_tax_id'), $customer->tax_id],
                ]" />
                <x-common.info-card :title="__('body.customer_contact')" :rows="[
                    [__('body.phone'), $customer->phone],
                    [__('body.email_address'), $customer->email],
                    [__('body.address'), $customer->address],
                    [__('body.customer_delivery_address'), $customer->delivery_address ?: $customer->address],
                ]" />
            </div>

            <div class="bg-white border border-line rounded-xl p-5 space-y-2">
                <h2 class="font-display font-bold text-ink">{{ __('body.customer_terms') }}</h2>
                <p class="text-sm text-body">{{ $customer->payment_terms_days ? __('body.customer_terms_days', ['days' => $customer->payment_terms_days]) : __('body.customer_terms_none') }}</p>
                @if ($customer->notes)
                    <p class="text-sm text-body leading-relaxed pt-2 border-t border-line">{!! nl2br(e($customer->notes)) !!}</p>
                @endif
            </div>

            {{-- Modules add their own panels here (orders, invoices, balance…) --}}
            @foreach (app(\App\Services\Module\ModuleManager::class)->hook('customer_panel', $customer) as $panel)
                @if (filled($panel)) {!! $panel !!} @endif
            @endforeach
        </div>

        <div class="space-y-4">
            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-display font-bold text-ink">{{ __('body.status') }}</h2>
                    <x-common.badge :color="$customer->status->color()">{{ $customer->status->label() }}</x-common.badge>
                </div>
                @if ($canUpdate)
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (['active' => 'success', 'inactive' => 'warning', 'banned' => 'danger'] as $status => $color)
                            @continue($customer->status->value === $status)
                            <x-common.action-button :action="route('customers.status', $customer)" method="PUT" :color="$color">
                                <x-slot:fields><input type="hidden" name="status" value="{{ $status }}"></x-slot:fields>
                                {{ __('body.customer_set_'.$status) }}
                            </x-common.action-button>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($portalAvailable)
                <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                    <h2 class="font-display font-bold text-ink">{{ __('body.customer_portal') }}</h2>
                    <p class="text-sm text-muted">{{ $customer->hasPortalPassword() ? __('body.customer_portal_on') : __('body.customer_portal_off') }}</p>
                    @if ($customer->last_login_at)
                        <p class="text-xs text-muted">{{ __('body.last_login') }}: @datetime($customer->last_login_at)</p>
                    @endif
                    @if ($canUpdate)
                        <div class="flex flex-wrap gap-1.5">
                            <x-common.action-button :action="route('customers.portal.invite', $customer)" color="brand">
                                {{ $customer->hasPortalPassword() ? __('body.customer_send_reset') : __('body.customer_send_invite') }}
                            </x-common.action-button>
                            @if ($customer->hasPortalPassword())
                                <x-common.action-button :action="route('customers.portal.revoke', $customer)" method="DELETE" color="danger">{{ __('body.customer_remove_portal') }}</x-common.action-button>
                            @endif
                        </div>
                    @endif
                    @if ($portalUrl)
                        <p class="text-xs text-muted break-all">{{ $portalUrl }}</p>
                    @endif
                </div>
            @endif

            @if (\App\Helpers\AuthHelper::can('delete customers.customer'))
                <div class="bg-white border border-danger/40 rounded-xl p-5 space-y-3">
                    <h2 class="font-display font-bold text-danger">{{ __('body.delete') }}</h2>
                    @if ($links !== [])
                        <p class="text-sm text-muted">{{ __('body.customer_has_links') }}</p>
                        <ul class="text-sm text-body list-disc pl-5">
                            @foreach ($links as $module => $count)
                                <li>{{ $module }}: {{ $count }}</li>
                            @endforeach
                        </ul>
                    @else
                        <form action="{{ route('customers.destroy', $customer) }}" method="POST">
                            @csrf @method('DELETE')
                            <x-common.button type="submit" variant="secondary" size="sm"
                                data-confirm data-confirm-title="{{ __('body.delete') }} {{ $customer->displayName() }}?" data-confirm-variant="danger">{{ __('body.customer_delete') }}</x-common.button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
