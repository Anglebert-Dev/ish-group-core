@php
    $canUpdate = \App\Helpers\AuthHelper::can('update fleet.vehicle');
    $insuranceLevel = $vehicle->insuranceAlertLevel();
    $inspectionLevel = $vehicle->inspectionAlertLevel();
    $complianceAttention = $insuranceLevel->isAttention() || $inspectionLevel->isAttention();
    $activeTab = request('tab', 'overview');
    if ($errors->hasAny([
        'insurance_company', 'insurance_expires_at', 'insurance_document',
        'inspection_done_at', 'inspection_expires_at', 'inspection_document',
        'carte_jaune_number', 'carte_jaune_issued_at', 'carte_jaune_document',
    ])) {
        $activeTab = 'compliance';
    }
    $tabs = [
        ['id' => 'overview', 'label' => __('body.overview'), 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['id' => 'specs', 'label' => __('body.specifications'), 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['id' => 'operational', 'label' => __('body.operational'), 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['id' => 'compliance', 'label' => __('body.compliance_documents'), 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'badge' => $complianceAttention ? '!' : null],
        ['id' => 'images', 'label' => __('body.images'), 'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'badge' => $vehicle->images->count() ?: null],
    ];
@endphp
<x-layouts.app title="{{ $vehicle->name() }}">
    <div data-details-page>
        <x-common.details.header
            :title="$vehicle->name()"
            :subtitle="__('body.vehicle_details')"
            :back-href="route('fleet.vehicles.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$vehicle->status->label()" />
                <span class="text-xs px-2 py-1 rounded font-semibold bg-surface text-body border border-line">{{ $vehicle->plate_number }}</span>
            </x-slot:meta>
            <x-slot:actions>
                @if ($canUpdate)
                    <x-common.button href="{{ route('fleet.vehicles.edit', $vehicle) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.plate_number')" :value="$vehicle->plate_number" />
            <x-common.details.summary-item :label="__('body.category')" :value="$vehicle->category?->name ?? '—'" />
            <x-common.details.summary-item :label="__('body.daily_rate')" :value="$vehicle->dailyPrice() !== null ? number_format($vehicle->dailyPrice(), 0).' RWF/day' : '—'" />
            <x-common.details.summary-item :label="__('body.station')" :value="$vehicle->station?->name ?? '—'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$vehicle->status->label()" />
            </x-common.details.summary-item>
            <x-slot:tabs>
                <x-common.details.tabs :tabs="$tabs" :active="$activeTab" />
            </x-slot:tabs>
        </x-common.details.summary>

        @if ($complianceAttention)
            <div class="mb-6 rounded-xl border px-4 py-3 {{ ($insuranceLevel === \App\Enums\DocumentAlertLevel::Red || $inspectionLevel === \App\Enums\DocumentAlertLevel::Red) ? 'border-red-100 bg-red-50/60' : 'border-amber-100 bg-amber-50/60' }}">
                <p class="text-sm font-semibold text-ink">{{ __('body.vehicle_compliance_alert') }}</p>
                <ul class="mt-2 space-y-1 text-sm text-body">
                    @if ($insuranceLevel->isAttention())
                        <li>{{ __('body.insurance') }}: {{ $vehicle->insuranceAlertSummary() }}</li>
                    @endif
                    @if ($inspectionLevel->isAttention())
                        <li>{{ __('body.inspection') }}: {{ $vehicle->inspectionAlertSummary() }}</li>
                    @endif
                </ul>
            </div>
        @endif

        @if ($canUpdate)
            <x-common.status-update
                :action="route('fleet.vehicles.status', $vehicle)"
                :value="$vehicle->status->value"
                :options="\App\Enums\VehicleStatus::cases()"
                :can="true"
            />
        @endif

        <div data-details-panel="overview" class="space-y-6">
            <x-common.details.section :title="__('body.basic_information')" icon="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                <x-common.details.grid :cols="3">
                    <x-common.details.field :label="__('body.plate_number')">{{ $vehicle->plate_number }}</x-common.details.field>
                    <x-common.details.field :label="__('body.vin')">{{ $vehicle->vin ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.color')">{{ $vehicle->color ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.category')">{{ $vehicle->category?->name ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.daily_rate')">{{ $vehicle->dailyPrice() !== null ? number_format($vehicle->dailyPrice(), 2).' RWF/day' : '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.station')">{{ $vehicle->station?->name ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.ownership')">{{ $vehicle->ownershipType()->label() }}</x-common.details.field>
                    <x-common.details.field :label="__('body.vehicle_owner')">
                        @if ($vehicle->owner)
                            <a href="{{ route('fleet.owners.show', $vehicle->owner) }}" class="hover:text-brand">{{ $vehicle->owner->fullName() }}</a>
                            <span class="text-muted text-sm font-medium">({{ $vehicle->owner->splitLabel() }})</span>
                        @else
                            {{ __('body.company_fleet') }}
                        @endif
                    </x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>
        </div>

        <div data-details-panel="specs" class="hidden space-y-6">
            <x-common.details.section :title="__('body.specifications')" icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                <x-common.details.grid :cols="3">
                    <x-common.details.field :label="__('body.vehicle_type')">{{ $vehicle->vehicle_type->label() }}</x-common.details.field>
                    <x-common.details.field :label="__('body.seats')">{{ $vehicle->seats }}</x-common.details.field>
                    <x-common.details.field :label="__('body.transmission')">{{ $vehicle->transmission->label() }}</x-common.details.field>
                    <x-common.details.field :label="__('body.fuel_type')">{{ $vehicle->fuel_type->label() }}</x-common.details.field>
                    <x-common.details.field :label="__('body.engine_capacity')">{{ $vehicle->engine_capacity ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.weight')">{{ $vehicle->weight !== null ? number_format($vehicle->weight, 2).' kg' : '—' }}</x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>
        </div>

        <div data-details-panel="operational" class="hidden space-y-6">
            <x-common.details.section :title="__('body.operational_information')" icon="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                <x-common.details.grid :cols="3">
                    <x-common.details.field :label="__('body.mileage')">{{ $vehicle->mileage !== null ? number_format($vehicle->mileage).' km' : '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.required_license_category')">{{ $vehicle->required_license_category?->label() ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.status')">
                        <x-common.status :status="$vehicle->status->label()" />
                    </x-common.details.field>
                    <x-common.details.field :label="__('body.vehicle_description')" :span="3">
                        <span class="whitespace-pre-line">{{ $vehicle->notes ?? '—' }}</span>
                    </x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>
        </div>

        <div data-details-panel="compliance" class="hidden space-y-6">
            <x-common.details.section :title="__('body.compliance_documents')" icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" tone="ink">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <x-common.document-tile
                        :title="__('body.insurance')"
                        :level="$insuranceLevel"
                        :summary="$vehicle->insuranceAlertSummary()"
                        :expiry="$vehicle->insurance_expires_at"
                        :meta="$vehicle->insurance_company ? __('body.insurance_company').': '.$vehicle->insurance_company : null"
                        :url="$vehicle->documentUrl($vehicle->insurance_document)"
                    />
                    <x-common.document-tile
                        :title="__('body.inspection')"
                        :level="$inspectionLevel"
                        :summary="$vehicle->inspectionAlertSummary()"
                        :expiry="$vehicle->inspection_expires_at"
                        :meta="$vehicle->inspection_done_at ? __('body.inspection_done_at').': '.$vehicle->inspection_done_at->format('d M Y') : null"
                        :url="$vehicle->documentUrl($vehicle->inspection_document)"
                    />
                    <x-common.document-tile
                        :title="__('body.carte_jaune')"
                        :show-expiry="false"
                        :meta="$vehicle->carte_jaune_number
                            ? __('body.carte_jaune_number').': '.$vehicle->carte_jaune_number
                                .($vehicle->carte_jaune_issued_at ? ' · '.__('body.issued').': '.$vehicle->carte_jaune_issued_at->format('d M Y') : '')
                            : ($vehicle->carte_jaune_document || $vehicle->carte_jaune_issued_at ? __('body.on_file') : __('body.not_set'))"
                        :url="$vehicle->documentUrl($vehicle->carte_jaune_document)"
                    />
                </div>
            </x-common.details.section>

            @if ($canUpdate)
                <x-common.details.section
                    :title="__('body.update_compliance_documents')"
                    icon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                    tone="brand"
                >
                    <p class="text-sm text-muted mb-4">{{ __('body.update_compliance_documents_hint') }}</p>
                    <form action="{{ route('fleet.vehicles.compliance', $vehicle) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <x-common.input name="insurance_company" :label="__('body.insurance_company')" :value="old('insurance_company', $vehicle->insurance_company)" />
                            <x-common.input type="date" name="insurance_expires_at" :label="__('body.insurance_expires_at')" :value="old('insurance_expires_at', $vehicle->insurance_expires_at?->format('Y-m-d'))" />
                            <x-common.file-input
                                name="insurance_document"
                                :label="__('body.insurance_document')"
                                accept=".pdf,image/*"
                                :max-size-mb="5"
                                :existing-url="$vehicle->documentUrl($vehicle->insurance_document)"
                                :hint="$vehicle->insurance_document ? __('body.current_file_kept') : null"
                                class="sm:col-span-2"
                            />

                            <x-common.input type="date" name="inspection_done_at" :label="__('body.inspection_done_at')" :value="old('inspection_done_at', $vehicle->inspection_done_at?->format('Y-m-d'))" />
                            <x-common.input type="date" name="inspection_expires_at" :label="__('body.inspection_expires_at')" :value="old('inspection_expires_at', $vehicle->inspection_expires_at?->format('Y-m-d'))" />
                            <x-common.file-input
                                name="inspection_document"
                                :label="__('body.inspection_document')"
                                accept=".pdf,image/*"
                                :max-size-mb="5"
                                :existing-url="$vehicle->documentUrl($vehicle->inspection_document)"
                                :hint="$vehicle->inspection_document ? __('body.current_file_kept') : null"
                                class="sm:col-span-2"
                            />

                            <x-common.input name="carte_jaune_number" :label="__('body.carte_jaune_number')" :value="old('carte_jaune_number', $vehicle->carte_jaune_number)" />
                            <x-common.input type="date" name="carte_jaune_issued_at" :label="__('body.carte_jaune_issued_at')" :value="old('carte_jaune_issued_at', $vehicle->carte_jaune_issued_at?->format('Y-m-d'))" />
                            <x-common.file-input
                                name="carte_jaune_document"
                                :label="__('body.carte_jaune_document')"
                                accept=".pdf,image/*"
                                :max-size-mb="5"
                                :existing-url="$vehicle->documentUrl($vehicle->carte_jaune_document)"
                                :hint="$vehicle->carte_jaune_document ? __('body.current_file_kept') : __('body.carte_jaune_hint')"
                                class="sm:col-span-2"
                            />
                        </div>
                        <x-common.button type="submit" size="sm">{{ __('body.save_compliance_details') }}</x-common.button>
                    </form>
                </x-common.details.section>
            @endif
        </div>

        <div data-details-panel="images" class="hidden space-y-6">
            <x-common.details.section :title="__('body.images')" icon="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" tone="ink">
                @if ($vehicle->images->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        @foreach ($vehicle->images as $image)
                            <button
                                type="button"
                                data-lightbox-src="{{ $image->url() }}"
                                data-lightbox-alt="{{ $vehicle->name() }}"
                                class="relative block w-full text-left rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/30 focus:ring-offset-2 group cursor-zoom-in"
                            >
                                <img
                                    src="{{ $image->url() }}"
                                    alt="{{ $vehicle->name() }}"
                                    class="w-full h-32 object-cover rounded-xl border border-line transition-opacity group-hover:opacity-90"
                                >
                                @if ($loop->first)
                                    <span class="absolute top-1.5 left-1.5 inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-brand text-white pointer-events-none">{{ __('body.cover_image') }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-muted">{{ __('body.no_images_yet') }}</p>
                @endif
            </x-common.details.section>
        </div>
    </div>

    <x-common.image-lightbox />
</x-layouts.app>
