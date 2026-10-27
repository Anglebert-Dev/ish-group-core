@php
    $credentials = session('portal_credentials');
@endphp

<x-layouts.app title="{{ $customer->name }}">
    <div data-details-page>
        <x-common.details.header
            :title="$customer->name"
            :subtitle="__('body.customer_details')"
            :back-href="route('customers.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="ucfirst($customer->status->value)" />
                @if ($customer->hasPortalPassword())
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-success/10 text-success">{{ __('body.portal_account_active') }}</span>
                @else
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-surface text-muted border border-line">{{ __('body.portal_account_missing') }}</span>
                @endif
            </x-slot:meta>
            <x-slot:actions>
                @if ($customer->hasPortalPassword())
                    <form action="{{ route('customers.reset-password', $customer) }}" method="POST">
                        @csrf
                        <x-common.button
                            type="submit"
                            variant="secondary"
                            size="sm"
                            data-confirm
                            data-confirm-title="{{ __('body.reset_portal_password') }}?"
                            data-confirm-message="{{ __('body.reset_portal_password_confirm') }}"
                            data-confirm-variant="brand"
                            data-confirm-text="{{ __('body.reset_portal_password') }}"
                        >{{ __('body.reset_portal_password') }}</x-common.button>
                    </form>
                @else
                    <form action="{{ route('customers.portal-account', $customer) }}" method="POST">
                        @csrf
                        <x-common.button
                            type="submit"
                            size="sm"
                            data-confirm
                            data-confirm-title="{{ __('body.generate_portal_account') }}?"
                            data-confirm-message="{{ __('body.generate_portal_account_confirm') }}"
                            data-confirm-variant="brand"
                            data-confirm-text="{{ __('body.generate_portal_account') }}"
                        >{{ __('body.generate_portal_account') }}</x-common.button>
                    </form>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.email_address')" :value="$customer->email" />
            <x-common.details.summary-item :label="__('body.phone')" :value="$customer->phone ?? '—'" />
            <x-common.details.summary-item :label="__('body.national_id')" :value="$customer->national_id ?? '—'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="ucfirst($customer->status->value)" />
            </x-common.details.summary-item>
            <x-common.details.summary-item :label="__('body.joined')" :value="$customer->created_at->format('d M Y')" />
        </x-common.details.summary>

        <x-common.status-update
            :action="route('customers.status', $customer)"
            :value="$customer->status->value"
            :options="\App\Enums\CustomerStatus::cases()"
            :can="\App\Helpers\AuthHelper::can('update customers.customer')"
        />


        <x-common.details.section
            :title="__('body.overview')"
            icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
        >
            <x-common.details.grid>
                <x-common.details.field :label="__('body.full_name')">{{ $customer->name }}</x-common.details.field>
                <x-common.details.field :label="__('body.email_address')">{{ $customer->email }}</x-common.details.field>
                <x-common.details.field :label="__('body.phone')">{{ $customer->phone ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.national_id')">{{ $customer->national_id ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.driver_license_id')">{{ $customer->driver_license_number ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.address')" :span="2">{{ $customer->address ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.portal_login')">
                    @if ($customer->hasPortalPassword())
                        {{ __('body.portal_account_active') }}
                    @else
                        {{ __('body.portal_account_missing') }}
                    @endif
                </x-common.details.field>
                <x-common.details.field :label="__('body.status')">
                    <x-common.status :status="ucfirst($customer->status->value)" />
                </x-common.details.field>
                <x-common.details.field :label="__('body.joined')">{{ $customer->created_at->format('d M Y') }}</x-common.details.field>
            </x-common.details.grid>
        </x-common.details.section>
    </div>

    @if (is_array($credentials))
        <div id="portal-credentials-modal" class="fixed inset-0 z-[100]">
            <div class="absolute inset-0 bg-ink/50" data-credentials-close></div>
            <div class="relative min-h-full flex items-end sm:items-center justify-center p-4 sm:p-6">
                <div class="bg-white rounded-t-2xl sm:rounded-xl shadow-xl max-w-md w-full p-5 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="portal-credentials-title">
                    <h3 id="portal-credentials-title" class="font-display font-bold text-lg text-ink">{{ __('body.portal_login_details') }}</h3>
                    <p class="text-sm text-muted mt-2 leading-relaxed">{{ __('body.portal_login_details_hint') }}</p>

                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="rounded-xl border border-line bg-surface px-4 py-3">
                            <dt class="text-xs font-semibold text-muted uppercase tracking-wide">{{ __('body.email_address') }}</dt>
                            <dd class="mt-1 font-semibold text-ink break-all" data-copy-value>{{ $credentials['email'] }}</dd>
                        </div>
                        @if (! empty($credentials['phone']))
                            <div class="rounded-xl border border-line bg-surface px-4 py-3">
                                <dt class="text-xs font-semibold text-muted uppercase tracking-wide">{{ __('body.phone_number') }}</dt>
                                <dd class="mt-1 font-semibold text-ink break-all" data-copy-value>{{ $credentials['phone'] }}</dd>
                            </div>
                        @endif
                        <div class="rounded-xl border border-line bg-surface px-4 py-3">
                            <dt class="text-xs font-semibold text-muted uppercase tracking-wide">{{ __('body.password') }}</dt>
                            <dd class="mt-1 font-semibold text-ink break-all font-mono" data-copy-value>{{ $credentials['password'] }}</dd>
                        </div>
                    </dl>

                    <p class="text-xs text-danger mt-4">{{ __('body.portal_login_details_once') }}</p>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-end gap-2 sm:gap-3 mt-6">
                        <x-common.button type="button" variant="secondary" size="sm" data-credentials-close>{{ __('body.close') }}</x-common.button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                var modal = document.getElementById('portal-credentials-modal');
                if (!modal) return;

                function closeModal() {
                    modal.remove();
                }

                modal.querySelectorAll('[data-credentials-close]').forEach(function (el) {
                    el.addEventListener('click', closeModal);
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') closeModal();
                });
            })();
        </script>
    @endif
</x-layouts.app>
