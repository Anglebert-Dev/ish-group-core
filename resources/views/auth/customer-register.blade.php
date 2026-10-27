@php
    $accountType = old('account_type');
    $isOrganization = $accountType === \App\Enums\CustomerAccountType::Organization->value;
@endphp

<x-layouts.auth
    :title="__('body.auth_create_account')"
    :heading="__('body.auth_register_title_customer')"
    :subtitle="__('body.auth_register_subtitle_customer')"
    :panel-text="__('body.auth_panel_text')"
>
    <form action="{{ route('register.store') }}" method="POST" class="space-y-4" id="customer-register-form">
        @csrf

        <div id="register-type-step">
            <p class="text-sm font-semibold text-ink">{{ __('body.auth_account_type') }}</p>
            <p class="text-sm text-muted mt-1 mb-4">{{ __('body.auth_account_type_hint') }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label for="account-type-personal" class="flex flex-col gap-2 p-4 rounded-xl border border-line cursor-pointer transition-all duration-200 has-[:checked]:border-brand has-[:checked]:bg-brand-light has-[:checked]:shadow-sm hover:border-brand/60">
                    <input
                        type="radio"
                        name="account_type"
                        id="account-type-personal"
                        value="{{ \App\Enums\CustomerAccountType::Personal->value }}"
                        class="sr-only"
                        @checked($accountType === \App\Enums\CustomerAccountType::Personal->value)
                        required
                    >
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface text-brand">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </span>
                    <span class="text-sm font-semibold text-ink">{{ __('body.auth_account_personal') }}</span>
                    <span class="text-xs text-muted leading-relaxed">{{ __('body.auth_account_personal_desc') }}</span>
                </label>

                <label for="account-type-organization" class="flex flex-col gap-2 p-4 rounded-xl border border-line cursor-pointer transition-all duration-200 has-[:checked]:border-brand has-[:checked]:bg-brand-light has-[:checked]:shadow-sm hover:border-brand/60">
                    <input
                        type="radio"
                        name="account_type"
                        id="account-type-organization"
                        value="{{ \App\Enums\CustomerAccountType::Organization->value }}"
                        class="sr-only"
                        @checked($isOrganization)
                    >
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface text-brand">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h1m-1 4h1m-1 4h1"/></svg>
                    </span>
                    <span class="text-sm font-semibold text-ink">{{ __('body.auth_account_organization') }}</span>
                    <span class="text-xs text-muted leading-relaxed">{{ __('body.auth_account_organization_desc') }}</span>
                </label>
            </div>

            @error('account_type')
                <p class="text-xs text-danger mt-2" role="alert">{{ $message }}</p>
            @enderror

            <button
                type="button"
                id="register-type-continue"
                class="mt-5 w-full h-12 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] transition-colors shadow-sm shadow-brand/25"
            >
                {{ __('body.next') }}
            </button>
        </div>

        <div id="register-details-step" class="space-y-4">
            <button
                type="button"
                id="register-type-back"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:text-brand-dark"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ __('body.auth_change_account_type') }}
            </button>

            <p id="register-type-summary" class="text-sm text-muted"></p>

            <x-common.input name="name" :label="__('body.full_name')" :value="old('name')" :placeholder="__('body.placeholder_full_name')" required autocomplete="name" />
            <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email')" :placeholder="__('body.placeholder_email')" required autocomplete="username" />
            <x-common.phone-input name="phone" :label="__('body.phone_number')" :value="old('phone')" :placeholder="__('body.phone_national_placeholder')" />

            <div id="register-organization-fields" class="space-y-4">
                <x-common.input name="company_name" :label="__('body.company_name')" :value="old('company_name')" :placeholder="__('body.placeholder_company_name')" autocomplete="organization" />
                <x-common.input name="tin" :label="__('body.tin')" :value="old('tin')" :placeholder="__('body.placeholder_tin')" />
            </div>

            <x-common.input type="password" name="password" :label="__('body.password')" :placeholder="__('body.placeholder_password_create')" required autocomplete="new-password" strength />
            <x-common.input type="password" name="password_confirmation" :label="__('body.confirm_password')" :placeholder="__('body.placeholder_password_confirm')" required autocomplete="new-password" confirm-for="field-password" />

            <button type="submit" class="w-full h-12 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] transition-colors shadow-sm shadow-brand/25">
                {{ __('body.auth_create_account') }}
            </button>
        </div>
    </form>

    <x-slot:footer>
        <p>
            {{ __('body.auth_have_account') }}
            <a href="{{ route('login') }}" class="font-semibold text-brand hover:text-brand-dark">{{ __('body.auth_sign_in') }}</a>
        </p>
    </x-slot:footer>

    @push('scripts')
    <script>
        (function () {
            var typeStep = document.getElementById('register-type-step');
            var detailsStep = document.getElementById('register-details-step');
            var orgFields = document.getElementById('register-organization-fields');
            var companyName = document.getElementById('field-company-name');
            var summary = document.getElementById('register-type-summary');
            var personal = document.getElementById('account-type-personal');
            var organization = document.getElementById('account-type-organization');
            var personalLabel = @json(__('body.auth_account_personal'));
            var organizationLabel = @json(__('body.auth_account_organization'));

            function selectedType() {
                if (organization && organization.checked) return 'organization';
                if (personal && personal.checked) return 'personal';
                return null;
            }

            function syncOrganizationFields() {
                var isOrg = selectedType() === 'organization';
                if (orgFields) orgFields.hidden = !isOrg;
                if (companyName) companyName.required = isOrg;
            }

            function showDetails() {
                if (!selectedType()) {
                    personal.setCustomValidity(@json(__('body.auth_account_type_required')));
                    personal.reportValidity();
                    return;
                }
                personal.setCustomValidity('');
                typeStep.hidden = true;
                detailsStep.hidden = false;
                syncOrganizationFields();
                if (summary) {
                    summary.textContent = selectedType() === 'organization' ? organizationLabel : personalLabel;
                }
                var nameField = document.getElementById('field-name');
                if (nameField) nameField.focus();
            }

            function showType() {
                detailsStep.hidden = true;
                typeStep.hidden = false;
            }

            document.getElementById('register-type-continue')?.addEventListener('click', showDetails);
            document.getElementById('register-type-back')?.addEventListener('click', showType);
            personal?.addEventListener('change', syncOrganizationFields);
            organization?.addEventListener('change', syncOrganizationFields);

            if (selectedType()) {
                typeStep.hidden = true;
                detailsStep.hidden = false;
                if (summary) {
                    summary.textContent = selectedType() === 'organization' ? organizationLabel : personalLabel;
                }
            } else {
                detailsStep.hidden = true;
            }

            syncOrganizationFields();
        })();
    </script>
    @endpush
</x-layouts.auth>
