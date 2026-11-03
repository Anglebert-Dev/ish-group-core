<x-layouts.app :title="$business->name">
    <x-common.page-header :title="$business->name" :description="$business->typeLabel().($profile->code ? ' · '.$profile->code : '')">
        <x-slot:actions>
            <x-common.button href="{{ route('injongepay.platform.businesses.index') }}" variant="secondary" size="sm">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <form action="{{ route('injongepay.platform.businesses.update', $business) }}" method="POST" class="xl:col-span-2 bg-card border border-line rounded-xl p-5 sm:p-6 space-y-5">
            @csrf @method('PUT')
            <div>
                <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.profile') }}</h2>
                <p class="text-sm text-muted mt-1">{{ __('injongepay::injongepay.profile_description') }}</p>
            </div>
            @php $canEdit = \App\Helpers\AuthHelper::can('update injongepay_platform.business'); @endphp
            <fieldset @disabled(! $canEdit) class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-common.input name="tin" :label="__('injongepay::injongepay.tin')" :value="old('tin', $profile->tin)" required inputmode="numeric" maxlength="9" />
                <x-common.input name="short_name" :label="__('injongepay::injongepay.short_name')" :value="old('short_name', $profile->short_name)" />
                <x-common.input name="contact_person" :label="__('injongepay::injongepay.kyc_contact_person')" :value="old('contact_person', $profile->contact_person)" required />
                <x-common.input name="contact_email" type="email" :label="__('injongepay::injongepay.kyc_contact_email')" :value="old('contact_email', $profile->contact_email)" required />
                <x-common.phone-input name="contact_phone" :label="__('injongepay::injongepay.kyc_contact_phone')" :value="old('contact_phone', $profile->contact_phone)" required />
                <x-common.input name="fee_percentage" type="number" step="0.01" min="0" max="100" :label="__('injongepay::injongepay.fee_percentage')"
                    :hint="__('injongepay::injongepay.fee_percentage_hint', ['rate' => $platformRate])" :value="old('fee_percentage', $profile->fee_percentage)" />
                <x-common.input name="country" :label="__('injongepay::injongepay.country')" :value="old('country', $profile->country ?? 'RW')" required maxlength="2" />
                <x-common.input name="city" :label="__('injongepay::injongepay.city')" :value="old('city', $profile->city)" />
                <x-common.input name="address" :label="__('injongepay::injongepay.address')" :value="old('address', $profile->address)" />
                <x-common.select name="support_staff_id" :label="__('injongepay::injongepay.support_staff')" :options="['' => '—'] + $staff" :value="old('support_staff_id', $profile->support_staff_id)" hide-placeholder />
            </fieldset>
            <p class="text-xs text-muted">{{ __('injongepay::injongepay.contact_phone_hint') }}</p>
            @if ($canEdit)
                <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
            @endif
        </form>

        <div class="bg-card border border-line rounded-xl p-5 space-y-3">
            <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.fee_history') }}</h2>
            @forelse ($feeHistory as $change)
                <div class="text-sm border-b border-line pb-2">
                    <p class="text-ink font-semibold">{{ $change->old_value ?? '—' }}% → {{ $change->new_value ?? '—' }}%</p>
                    <p class="text-muted text-xs">{{ \App\Libraries\GeneralLibrary::formatDateTime($change->created_at) }} · {{ $change->changed_by_name ?? '—' }}</p>
                </div>
            @empty
                <p class="text-sm text-muted">{{ __('injongepay::injongepay.no_fee_history') }}</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
