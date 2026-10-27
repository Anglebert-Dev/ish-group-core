<x-frontend::layouts.public :title="__('body.profile')" :panel="true">
    <x-common.page-header :title="__('body.profile')" :description="__('body.profile_description')" />

    <div class="w-full space-y-6">
        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 w-full">
            <h2 class="font-display font-bold text-ink">{{ __('body.account_details') }}</h2>
            <p class="text-sm text-muted mt-1 mb-5">{{ __('body.account_details_description') }}</p>

            <form action="{{ route('profile.update') }}" method="POST" class="space-y-5">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-common.input name="name" :label="__('body.full_name')" :value="old('name', $customer->name)" required />
                    <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email', $customer->email)" required />
                </div>
                    <x-common.phone-input name="phone" :label="__('body.phone')" :value="old('phone', $customer->phone)" />
                <div class="flex justify-end pt-2 border-t border-line">
                    <x-common.button type="submit">{{ __('body.save_changes') }}</x-common.button>
                </div>
            </form>
        </div>

        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 w-full">
            <h2 class="font-display font-bold text-ink">{{ __('body.change_password') }}</h2>
            <p class="text-sm text-muted mt-1 mb-5">{{ __('body.change_password_description') }}</p>

            <form action="{{ route('profile.password') }}" method="POST" class="space-y-5">
                @csrf
                @method('PUT')
                <x-common.input type="password" name="current_password" :label="__('body.current_password')" required autocomplete="current-password" />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-common.input type="password" name="password" :label="__('body.new_password')" required autocomplete="new-password" strength />
                    <x-common.input type="password" name="password_confirmation" :label="__('body.confirm_password')" required autocomplete="new-password" confirm-for="field-password" />
                </div>
                <div class="flex justify-end pt-2 border-t border-line">
                    <x-common.button type="submit">{{ __('body.update_password') }}</x-common.button>
                </div>
            </form>
        </div>

        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 w-full flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-display font-bold text-ink">{{ __('body.log_out') }}</h2>
                <p class="text-sm text-muted mt-1">{{ __('body.log_out_of_account') }}</p>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <x-common.button type="submit" variant="danger">{{ __('body.log_out') }}</x-common.button>
            </form>
        </div>
    </div>
</x-frontend::layouts.public>
