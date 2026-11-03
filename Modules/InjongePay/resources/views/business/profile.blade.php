<x-layouts.app :title="__('injongepay::injongepay.business_profile')">
    <x-common.page-header :title="__('injongepay::injongepay.business_profile')" :description="__('injongepay::injongepay.business_profile_description')" />
    @include('injongepay::partials.test-mode')

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <form action="{{ route('injongepay.profile.update') }}" method="POST" class="xl:col-span-2 bg-card border border-line rounded-xl p-5 sm:p-6 space-y-4">
            @csrf @method('PUT')
            @php $canEdit = \App\Helpers\AuthHelper::can('update injongepay.profile'); @endphp
            <fieldset @disabled(! $canEdit) class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-common.input name="contact_person" :label="__('injongepay::injongepay.kyc_contact_person')" :value="old('contact_person', $profile->contact_person)" required />
                <x-common.input name="contact_email" type="email" :label="__('injongepay::injongepay.kyc_contact_email')" :value="old('contact_email', $profile->contact_email)" required />
                <x-common.phone-input name="contact_phone" :label="__('injongepay::injongepay.kyc_contact_phone')" :value="old('contact_phone', $profile->contact_phone)" required />
                <x-common.input name="short_name" :label="__('injongepay::injongepay.short_name')" :value="old('short_name', $profile->short_name)" />
                <x-common.input name="city" :label="__('injongepay::injongepay.city')" :value="old('city', $profile->city)" />
                <x-common.input name="address" :label="__('injongepay::injongepay.address')" :value="old('address', $profile->address)" />
            </fieldset>
            @if ($canEdit)
                <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
            @endif
        </form>
        <x-common.info-card :title="__('injongepay::injongepay.set_by_inhills')" :rows="[
            [__('injongepay::injongepay.tin'), $profile->tin ?? '—'],
            [__('injongepay::injongepay.fee'), $fee.'%'],
            [__('injongepay::injongepay.code'), $profile->code ?? '—'],
        ]" />
    </div>
</x-layouts.app>
