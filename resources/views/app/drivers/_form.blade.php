@php
    $driver = $driver ?? null;
    $cancelHref = $cancelHref ?? route('drivers.index');
@endphp

<x-common.wizard.step :step="1" :title="__('body.identity')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input name="first_name" :label="__('body.first_name')" :value="old('first_name', $driver?->first_name)" required />
        <x-common.input name="last_name" :label="__('body.last_name')" :value="old('last_name', $driver?->last_name)" required />
        <x-common.phone-input name="phone" :label="__('body.phone')" :value="old('phone', $driver?->phone)" required />
        <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email', $driver?->email)" />
        <x-common.input name="national_id" :label="__('body.national_id')" :value="old('national_id', $driver?->national_id)" required />
        <x-common.textarea name="address" :label="__('body.address')" :value="old('address', $driver?->address)" :rows="2" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.license_employment')">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input name="license_number" :label="__('body.license_number')" :value="old('license_number', $driver?->license_number)" required />
        <x-common.input type="date" name="license_expiry" :label="__('body.license_expiry')" :value="old('license_expiry', $driver?->license_expiry?->format('Y-m-d'))" required />
        <x-common.select name="license_category" :label="__('body.license_category')" :value="old('license_category', $driver?->license_category?->value)" :options="\App\Enums\LicenceCategory::cases()" :placeholder="'—'" />
        <x-common.input amount name="daily_rate" :label="__('body.daily_rate')" :value="old('daily_rate', $driver?->daily_rate)" min="0" />
        <x-common.select name="employment_status" :label="__('body.employment_status')" :value="old('employment_status', $driver?->employment_status?->value)" :options="\App\Enums\DriverEmploymentType::cases()" :hide-placeholder="true" />
        <x-common.select name="availability_status" :label="__('body.availability_status')" :value="old('availability_status', $driver?->availability_status?->value)" :options="\App\Enums\DriverAvailabilityStatus::cases()" :hide-placeholder="true" />
        <x-common.select name="status" :label="__('body.status')" :value="old('status', $driver?->status?->value)" :options="\App\Enums\DriverStatus::cases()" :hide-placeholder="true" />
    </div>
    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="3" :title="__('body.documents')">
    @if ($driver?->exists && $driver->documents->isNotEmpty())
        <div class="space-y-2">
            @foreach ($driver->documents as $document)
                <div class="flex items-center justify-between gap-2 border border-line rounded-lg px-3 py-2.5">
                    <a href="{{ route('drivers.documents.download', [$driver, $document]) }}" class="min-w-0 hover:text-brand">
                        <p class="text-sm text-ink truncate">{{ $document->original_name }}</p>
                        <p class="text-xs text-muted">{{ ucfirst(str_replace('_', ' ', $document->type)) }}</p>
                    </a>
                    <button type="button" form="delete-document-{{ $document->id }}" class="text-danger hover:opacity-80 text-xs font-medium"
                        data-confirm
                        data-confirm-title="{{ __('body.delete') }} {{ $document->original_name }}?"
                        data-confirm-variant="danger"
                        data-confirm-text="{{ __('body.delete') }}"
                    >{{ __('body.delete') }}</button>
                </div>
            @endforeach
        </div>

        @push('driver-document-delete-forms')
            @foreach ($driver->documents as $document)
                <form id="delete-document-{{ $document->id }}" action="{{ route('drivers.documents.destroy', [$driver, $document]) }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        @endpush
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        @foreach (['license' => 'License Copy', 'national_id' => 'National ID', 'contract' => 'Contract', 'other' => 'Other'] as $type => $label)
            <x-common.file-input
                :name="'documents['.$type.']'"
                :label="$label"
                accept=".pdf,image/*"
                :max-size-mb="5"
            />
        @endforeach
    </div>
    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="4" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl>
        <x-common.wizard.review-row :label="__('body.first_name')" for="first_name" />
        <x-common.wizard.review-row :label="__('body.last_name')" for="last_name" />
        <x-common.wizard.review-row :label="__('body.phone')" for="phone" />
        <x-common.wizard.review-row :label="__('body.national_id')" for="national_id" />
        <x-common.wizard.review-row :label="__('body.license_number')" for="license_number" />
        <x-common.wizard.review-row :label="__('body.license_expiry')" for="license_expiry" />
        <x-common.wizard.review-row :label="__('body.employment_status')" for="employment_status" />
        <x-common.wizard.review-row :label="__('body.availability_status')" for="availability_status" />
        <x-common.wizard.review-row :label="__('body.status')" for="status" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>
