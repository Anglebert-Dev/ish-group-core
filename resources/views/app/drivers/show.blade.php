@php
    $canUpdateDriver = \App\Helpers\AuthHelper::can('update drivers.driver');
    $activeTab = request('tab', 'overview');
    if ($errors->hasAny(['national_id', 'license_number', 'license_expiry', 'license_category', 'license_document'])) {
        $activeTab = 'overview';
    }
    $tabs = [
        ['id' => 'overview', 'label' => __('body.overview'), 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ['id' => 'documents', 'label' => __('body.documents'), 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'badge' => $driver->documents->count() ?: null],
    ];
@endphp
<x-layouts.app title="{{ $driver->fullName() }}">
    <div data-details-page>
        <x-common.details.header
            :title="$driver->fullName()"
            :subtitle="__('body.driver_details')"
            :back-href="route('drivers.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="ucfirst($driver->status->value)" />
                <x-common.status :status="$driver->availability_status->label()" />
            </x-slot:meta>
            <x-slot:actions>
                <x-common.button href="{{ route('drivers.edit', $driver) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.phone')" :value="$driver->phone" />
            <x-common.details.summary-item :label="__('body.national_id')" :value="$driver->national_id ?? '—'" />
            <x-common.details.summary-item :label="__('body.license_category')" :value="$driver->license_category?->label() ?? '—'" />
            <x-common.details.summary-item :label="__('body.license_expiry')">
                <x-common.document-alert :level="$driver->licenseAlertLevel()" :summary="$driver->licenseAlertSummary()" />
            </x-common.details.summary-item>
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="ucfirst($driver->status->value)" />
            </x-common.details.summary-item>
            <x-slot:tabs>
                <x-common.details.tabs :tabs="$tabs" :active="$activeTab" />
            </x-slot:tabs>
        </x-common.details.summary>

        @if ($driver->licenseAlertLevel()->isAttention())
            <div class="mb-6 rounded-xl border px-4 py-3 {{ $driver->licenseAlertLevel() === \App\Enums\DocumentAlertLevel::Red ? 'border-red-100 bg-red-50/60' : 'border-amber-100 bg-amber-50/60' }}">
                <p class="text-sm font-semibold text-ink">{{ __('body.license_expiry_alert') }}</p>
                <p class="text-sm text-body mt-1">{{ $driver->licenseAlertSummary() }}</p>
            </div>
        @endif

        <x-common.status-update
            :action="route('drivers.status', $driver)"
            :value="$driver->status->value"
            :options="\App\Enums\DriverStatus::cases()"
            :can="$canUpdateDriver"
        />


        <div data-details-panel="overview" class="space-y-6">
            <x-common.details.section :title="__('body.overview')" icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                <x-common.details.grid>
                    <x-common.details.field :label="__('body.phone')">{{ $driver->phone }}</x-common.details.field>
                    <x-common.details.field :label="__('body.email_address')">{{ $driver->email ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.national_id')">{{ $driver->national_id ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.address')">{{ $driver->address ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.license_number')">{{ $driver->license_number }}</x-common.details.field>
                    <x-common.details.field :label="__('body.license_expiry')">
                        <div class="space-y-1">
                            <p>{{ $driver->license_expiry?->format('d M Y') ?? '—' }}</p>
                            <x-common.document-alert :level="$driver->licenseAlertLevel()" :summary="$driver->licenseAlertSummary()" />
                        </div>
                    </x-common.details.field>
                    <x-common.details.field :label="__('body.license_category')">{{ $driver->license_category?->label() ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.daily_rate')">{{ $driver->daily_rate !== null ? number_format($driver->daily_rate, 0).' RWF/day' : '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.employment_status')">{{ $driver->employment_status->label() }}</x-common.details.field>
                    <x-common.details.field :label="__('body.availability_status')">
                        <x-common.status :status="$driver->availability_status->label()" />
                    </x-common.details.field>
                    <x-common.details.field :label="__('body.status')">
                        <x-common.status :status="ucfirst($driver->status->value)" />
                    </x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>

            @if ($canUpdateDriver)
                <x-common.details.section
                    :title="__('body.update_driving_license')"
                    icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    tone="brand"
                >
                    <p class="text-sm text-muted mb-4">{{ __('body.update_driving_license_hint') }}</p>
                    <form action="{{ route('drivers.license', $driver) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-common.input name="national_id" :label="__('body.national_id')" :value="old('national_id', $driver->national_id)" required />
                            <x-common.input name="license_number" :label="__('body.license_number')" :value="old('license_number', $driver->license_number)" required />
                            <x-common.input type="date" name="license_expiry" :label="__('body.license_expiry')" :value="old('license_expiry', $driver->license_expiry?->format('Y-m-d'))" required />
                            <x-common.select name="license_category" :label="__('body.license_category')" :value="old('license_category', $driver->license_category?->value)" :options="\App\Enums\LicenceCategory::cases()" :placeholder="'—'" />
                            <x-common.file-input
                                name="license_document"
                                :label="__('body.license_document')"
                                accept=".pdf,image/*"
                                :max-size-mb="5"
                                :hint="__('body.license_document_hint')"
                                class="sm:col-span-2"
                            />
                        </div>
                        <x-common.button type="submit" size="sm">{{ __('body.save_license_details') }}</x-common.button>
                    </form>
                </x-common.details.section>
            @endif
        </div>

        <div data-details-panel="documents" class="hidden space-y-6">
            <x-common.details.section :title="__('body.documents')" icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" tone="ink">
                <div class="space-y-2">
                    @forelse ($driver->documents as $document)
                        <div class="flex items-center justify-between gap-2 border border-line rounded-lg px-3 py-2.5 bg-surface">
                            <a href="{{ route('drivers.documents.download', [$driver, $document]) }}" class="min-w-0 hover:text-brand">
                                <p class="text-sm text-ink font-semibold truncate">{{ $document->original_name }}</p>
                                <p class="text-xs text-muted">{{ ucfirst(str_replace('_', ' ', $document->type)) }}</p>
                            </a>
                        </div>
                    @empty
                        <p class="text-sm text-muted">{{ __('body.no_documents_yet') }}</p>
                    @endforelse
                </div>
            </x-common.details.section>
        </div>
    </div>
</x-layouts.app>
