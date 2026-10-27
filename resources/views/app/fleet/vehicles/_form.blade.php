@php
    use App\Enums\VehicleColor;
    use App\Enums\VehicleOwnershipType;
    use App\Libraries\BrandLibrary;
    use App\Libraries\VehicleCatalogLibrary;

    $vehicle = $vehicle ?? null;
    $cancelHref = $cancelHref ?? route('fleet.vehicles.index');
    $owners = $owners ?? collect();
    $defaultOwnership = old(
        'ownership_type',
        $vehicle?->ownershipType()->value ?? VehicleOwnershipType::Company->value
    );
    $selectedMake = old('make', $vehicle?->make);
    $selectedModel = old('model', $vehicle?->model);
    $makeOptions = array_map(
        fn (string $make) => [
            'value' => $make,
            'label' => $make,
            'logo' => BrandLibrary::logoUrl($make),
        ],
        VehicleCatalogLibrary::makeOptions($vehicle)
    );
    $modelOptions = VehicleCatalogLibrary::modelOptions($selectedMake, $vehicle);
    $yearOptions = VehicleCatalogLibrary::years();
    $modelDisabled = ! filled($selectedMake);
@endphp

<x-common.wizard.step :step="1" :title="__('body.basic_information')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <x-common.select
            name="make"
            :label="__('body.make')"
            :value="$selectedMake"
            :options="$makeOptions"
            required
            placeholder="—"
            searchable
            data-vehicle-make
            data-option-logos
        />
        <x-common.select
            name="model"
            :label="__('body.model')"
            :value="$selectedModel"
            :options="$modelOptions"
            required
            placeholder="—"
            searchable
            data-vehicle-model
            :disabled="$modelDisabled"
        />
        <x-common.select
            name="year"
            :label="__('body.year')"
            :value="old('year', $vehicle?->year)"
            :options="$yearOptions"
            required
            placeholder="—"
        />
        <x-common.input
            name="plate_number"
            :label="__('body.plate_number')"
            :value="old('plate_number', $vehicle?->plate_number)"
            :placeholder="__('body.placeholder_plate_number')"
            :hint="__('body.plate_number_hint')"
            required
            maxlength="9"
            pattern="R[A-Za-z]{2} [0-9]{3} [A-Za-z]"
            title="{{ __('body.plate_number_hint') }}"
            autocomplete="off"
            spellcheck="false"
            autocapitalize="characters"
            inputmode="text"
            data-rwanda-plate
        />
        <x-common.input name="vin" :label="__('body.vin')" :value="old('vin', $vehicle?->vin)" />
        <x-common.select
            name="color"
            :label="__('body.color')"
            :value="old('color', $vehicle?->color)"
            :options="VehicleColor::options(old('color', $vehicle?->color))"
            placeholder="—"
        />
        <x-common.select
            name="vehicle_category_id"
            :label="__('body.category')"
            :value="old('vehicle_category_id', $vehicle?->vehicle_category_id)"
            required
            placeholder="—"
            :hint="__('body.vehicle_category_auto_hint')"
            :searchable="false"
            data-vehicle-category-select
        >
            <option value="">—</option>
            @foreach ($categories as $cat)
                <option
                    value="{{ $cat->id }}"
                    data-suggested-price="{{ (float) $cat->daily_price }}"
                    data-category-name="{{ $cat->name }}"
                    @selected((int) old('vehicle_category_id', $vehicle?->vehicle_category_id) === $cat->id)
                >{{ $cat->name }}</option>
            @endforeach
        </x-common.select>
        <x-common.input
            amount
            name="daily_price"
            :label="__('body.daily_rate').' (RWF)'"
            :value="old('daily_price', $vehicle?->daily_price)"
            required
            min="0"
            :hint="__('body.vehicle_daily_price_hint')"
            data-vehicle-daily-price
        />
        <x-common.select name="station_id" :label="__('body.station')" :value="old('station_id', $vehicle?->station_id)" :options="$stations->pluck('name', 'id')" :placeholder="'—'" required />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.ownership')">
    <p class="text-sm text-muted mb-4">{{ __('body.ownership_hint') }}</p>

    <div class="grid grid-cols-2 gap-3 max-w-md" data-ownership-type>
        <x-common.choice
            name="ownership_type"
            :value="VehicleOwnershipType::Company->value"
            :label="__('body.company_fleet')"
            id="ownership-type-company"
            :checked="$defaultOwnership === VehicleOwnershipType::Company->value"
        />
        <x-common.choice
            name="ownership_type"
            :value="VehicleOwnershipType::Outsider->value"
            :label="__('body.outsider_owner')"
            id="ownership-type-outsider"
            :checked="$defaultOwnership === VehicleOwnershipType::Outsider->value"
        />
    </div>
    @error('ownership_type')<p class="text-xs text-danger mt-1.5">{{ $message }}</p>@enderror

    <div id="outsider-owner-field" class="mt-5 max-w-md" data-wizard-show-when="ownership_type:outsider" @if ($defaultOwnership !== VehicleOwnershipType::Outsider->value) hidden @endif>
        <x-common.select
            name="vehicle_owner_id"
            :label="__('body.vehicle_owner')"
            :value="old('vehicle_owner_id', $vehicle?->vehicle_owner_id)"
            :hint="__('body.outsider_owner_hint')"
            :required="$defaultOwnership === VehicleOwnershipType::Outsider->value"
            :disabled="$defaultOwnership !== VehicleOwnershipType::Outsider->value"
        >
            <option value="">—</option>
            @foreach ($owners as $owner)
                <option value="{{ $owner->id }}" @selected((int) old('vehicle_owner_id', $vehicle?->vehicle_owner_id) === $owner->id)>{{ $owner->fullName() }} ({{ $owner->splitLabel() }})</option>
            @endforeach
        </x-common.select>
        @if ($owners->isEmpty())
            <p class="text-xs text-muted mt-2">
                {{ __('body.no_vehicle_owners_yet') }}
                <a href="{{ route('fleet.owners.create') }}" class="text-brand hover:text-brand-dark font-medium">{{ __('body.add_vehicle_owner') }}</a>
            </p>
        @endif
    </div>

    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="3" :title="__('body.specifications')">
    <p class="text-sm text-muted -mt-1">{{ __('body.vehicle_specs_auto_hint') }}</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <x-common.select name="vehicle_type" :label="__('body.vehicle_type')" :value="old('vehicle_type', $vehicle?->vehicle_type?->value)" :options="\App\Enums\VehicleType::cases()" :hide-placeholder="true" required data-vehicle-spec="vehicle_type" />
        <x-common.input type="number" name="seats" :label="__('body.seats')" :value="old('seats', $vehicle?->seats)" required min="1" max="100" data-vehicle-spec="seats" />
        <x-common.select name="transmission" :label="__('body.transmission')" :value="old('transmission', $vehicle?->transmission?->value)" :options="\App\Enums\TransmissionType::cases()" :hide-placeholder="true" required data-vehicle-spec="transmission" />
        <x-common.select name="fuel_type" :label="__('body.fuel_type')" :value="old('fuel_type', $vehicle?->fuel_type?->value)" :options="\App\Enums\FuelType::cases()" :hide-placeholder="true" required data-vehicle-spec="fuel_type" />
        <x-common.input name="engine_capacity" :label="__('body.engine_capacity')" :value="old('engine_capacity', $vehicle?->engine_capacity)" placeholder="e.g. 2.0L" />
        <x-common.input type="number" name="weight" :label="__('body.weight')" :value="old('weight', $vehicle?->weight)" step="0.01" min="0" />
    </div>
    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="4" :title="__('body.operational_information')">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <x-common.input type="number" name="mileage" :label="__('body.mileage').' (km)'" :value="old('mileage', $vehicle?->mileage)" min="0" />
        <x-common.select name="required_license_category" :label="__('body.required_license_category')" :value="old('required_license_category', $vehicle?->required_license_category?->value)" :options="\App\Enums\LicenceCategory::cases()" :placeholder="'—'" data-vehicle-spec="required_license_category" />
        <x-common.select name="status" :label="__('body.status')" :value="old('status', $vehicle?->status?->value)" :options="\App\Enums\VehicleStatus::cases()" :hide-placeholder="true" />
        <x-common.textarea name="notes" :label="__('body.vehicle_description')" :value="old('notes', $vehicle?->notes)" :rows="4" :hint="__('body.vehicle_description_hint')" class="sm:col-span-2 lg:col-span-3" />
    </div>
    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="5" :title="__('body.compliance_documents')">
    <p class="text-sm text-muted mb-4">{{ __('body.compliance_documents_hint') }}</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input name="insurance_company" :label="__('body.insurance_company')" :value="old('insurance_company', $vehicle?->insurance_company)" />
        <x-common.input type="date" name="insurance_expires_at" :label="__('body.insurance_expires_at')" :value="old('insurance_expires_at', $vehicle?->insurance_expires_at?->format('Y-m-d'))" />
        <x-common.file-input
            name="insurance_document"
            :label="__('body.insurance_document')"
            accept=".pdf,image/*"
            :max-size-mb="5"
            :existing-url="$vehicle?->documentUrl($vehicle?->insurance_document)"
            :hint="$vehicle?->insurance_document ? __('body.current_file_kept') : null"
            class="sm:col-span-2"
        />

        <x-common.input type="date" name="inspection_done_at" :label="__('body.inspection_done_at')" :value="old('inspection_done_at', $vehicle?->inspection_done_at?->format('Y-m-d'))" />
        <x-common.input type="date" name="inspection_expires_at" :label="__('body.inspection_expires_at')" :value="old('inspection_expires_at', $vehicle?->inspection_expires_at?->format('Y-m-d'))" />
        <x-common.file-input
            name="inspection_document"
            :label="__('body.inspection_document')"
            accept=".pdf,image/*"
            :max-size-mb="5"
            :existing-url="$vehicle?->documentUrl($vehicle?->inspection_document)"
            :hint="$vehicle?->inspection_document ? __('body.current_file_kept') : null"
            class="sm:col-span-2"
        />

        <x-common.input name="carte_jaune_number" :label="__('body.carte_jaune_number')" :value="old('carte_jaune_number', $vehicle?->carte_jaune_number)" />
        <x-common.input type="date" name="carte_jaune_issued_at" :label="__('body.carte_jaune_issued_at')" :value="old('carte_jaune_issued_at', $vehicle?->carte_jaune_issued_at?->format('Y-m-d'))" />
        <x-common.file-input
            name="carte_jaune_document"
            :label="__('body.carte_jaune_document')"
            accept=".pdf,image/*"
            :max-size-mb="5"
            :existing-url="$vehicle?->documentUrl($vehicle?->carte_jaune_document)"
            :hint="$vehicle?->carte_jaune_document ? __('body.current_file_kept') : __('body.carte_jaune_hint')"
            class="sm:col-span-2"
        />
    </div>
    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="6" :title="__('body.images')">
    @if ($vehicle?->exists && $vehicle->images->isNotEmpty())
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach ($vehicle->images as $image)
                <div class="relative group">
                    <img src="{{ $image->url() }}" alt="{{ $vehicle->name() }}" class="w-full h-32 object-cover rounded-lg border border-line">
                    @if ($loop->first)
                        <span class="absolute top-1.5 left-1.5 inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-brand text-white">{{ __('body.cover_image') }}</span>
                    @endif
                    <button type="button" form="delete-image-{{ $image->id }}"
                        class="absolute top-1.5 right-1.5 h-6 w-6 flex items-center justify-center rounded-md bg-white/90 text-danger hover:bg-white text-xs font-bold"
                        data-confirm
                        data-confirm-title="{{ __('body.delete') }} this image?"
                        data-confirm-variant="danger"
                        data-confirm-text="{{ __('body.delete') }}"
                    >&times;</button>
                </div>
            @endforeach
        </div>

        @push('vehicle-image-delete-forms')
            @foreach ($vehicle->images as $image)
                <form id="delete-image-{{ $image->id }}" action="{{ route('fleet.vehicles.images.destroy', [$vehicle, $image]) }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        @endpush
    @endif

    <x-common.file-input
        name="images[]"
        :label="__('body.upload_images')"
        accept="image/*"
        :multiple="true"
        :max-size-mb="4"
        :hint="__('body.current_file_kept')"
    />
    @error('images.*')<p class="text-xs text-danger mt-1.5">{{ $message }}</p>@enderror

    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="7" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl class="space-y-0">
        <x-common.wizard.review-row :label="__('body.make')" for="make" />
        <x-common.wizard.review-row :label="__('body.model')" for="model" />
        <x-common.wizard.review-row :label="__('body.year')" for="year" />
        <x-common.wizard.review-row :label="__('body.plate_number')" for="plate_number" />
        <x-common.wizard.review-row :label="__('body.color')" for="color" />
        <x-common.wizard.review-row :label="__('body.category')" for="vehicle_category_id" />
        <x-common.wizard.review-row :label="__('body.daily_rate')" for="daily_price" />
        <x-common.wizard.review-row :label="__('body.station')" for="station_id" />
        <x-common.wizard.review-row :label="__('body.ownership')" for="ownership_type" />
        <x-common.wizard.review-row :label="__('body.vehicle_owner')" for="vehicle_owner_id" />
        <x-common.wizard.review-row :label="__('body.vehicle_type')" for="vehicle_type" />
        <x-common.wizard.review-row :label="__('body.transmission')" for="transmission" />
        <x-common.wizard.review-row :label="__('body.fuel_type')" for="fuel_type" />
        <x-common.wizard.review-row :label="__('body.insurance_company')" for="insurance_company" />
        <x-common.wizard.review-row :label="__('body.insurance_expires_at')" for="insurance_expires_at" />
        <x-common.wizard.review-row :label="__('body.inspection_expires_at')" for="inspection_expires_at" />
        <x-common.wizard.review-row :label="__('body.carte_jaune_number')" for="carte_jaune_number" />
        <x-common.wizard.review-row :label="__('body.status')" for="status" />
        <x-common.wizard.review-row :label="__('body.images')" for="images[]" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>

@once
    @push('scripts')
        <script>
            (function () {
                var categorySelect = document.querySelector('[data-vehicle-category-select], select[name="vehicle_category_id"]');
                var priceInput = document.querySelector('[data-vehicle-daily-price], input[name="daily_price"]');
                var makeSelect = document.querySelector('[data-vehicle-make], select[name="make"]');
                var modelSelect = document.querySelector('[data-vehicle-model], select[name="model"]');
                var modelsUrl = @json(route('fleet.vehicles.catalog.models'));
                var selectedModel = @json($selectedModel);
                var lastSuggested = null;
                var modelsRequest = 0;
                var modelsAbort = null;
                var modelCategories = {};
                var modelSpecs = {};
                var autoCategoryLocked = @json((bool) ($vehicle?->exists && ! old('make') && ! old('model')));
                var autoSpecsLocked = @json((bool) ($vehicle?->exists && ! old('make') && ! old('model')));
                var modelsFetchTimeoutMs = 8000;
                var lastAutoSpecs = {};

                function formatRwandaPlate(value) {
                    var filtered = '';
                    var chars = String(value || '').toUpperCase();
                    for (var i = 0; i < chars.length; i++) {
                        var ch = chars.charAt(i);
                        if (filtered.length >= 7) {
                            break;
                        }
                        var pos = filtered.length;
                        // LLL DDD L — first letter must be R (civilian Rwandan plates).
                        if (pos === 0) {
                            if (ch === 'R') {
                                filtered += ch;
                            }
                        } else if (pos < 3) {
                            if (/[A-Z]/.test(ch)) {
                                filtered += ch;
                            }
                        } else if (pos < 6) {
                            if (/[0-9]/.test(ch)) {
                                filtered += ch;
                            }
                        } else if (/[A-Z]/.test(ch)) {
                            filtered += ch;
                        }
                    }

                    var formatted = '';
                    for (var j = 0; j < filtered.length; j++) {
                        if (j === 3 || j === 6) {
                            formatted += ' ';
                        }
                        formatted += filtered.charAt(j);
                    }
                    return formatted;
                }

                document.querySelectorAll('[data-rwanda-plate]').forEach(function (input) {
                    function applyFormat(keepCaret) {
                        var previous = input.value;
                        var formatted = formatRwandaPlate(previous);
                        if (formatted === previous) {
                            return;
                        }
                        var start = input.selectionStart;
                        input.value = formatted;
                        if (keepCaret && typeof start === 'number') {
                            var next = Math.max(0, start + (formatted.length - previous.length));
                            input.setSelectionRange(next, next);
                        }
                    }

                    input.addEventListener('input', function () {
                        applyFormat(true);
                    });
                    input.addEventListener('blur', function () {
                        applyFormat(false);
                    });
                    applyFormat(false);
                });

                function suggestedPrice() {
                    if (!categorySelect) return null;
                    var option = categorySelect.options[categorySelect.selectedIndex];
                    if (!option || !option.value) return null;
                    var raw = option.getAttribute('data-suggested-price');
                    return raw === null || raw === '' ? null : raw;
                }

                function refreshSelect(select) {
                    if (select && window.SearchableSelect && typeof window.SearchableSelect.refresh === 'function') {
                        window.SearchableSelect.refresh(select);
                    }
                }

                function findCategoryOption(name) {
                    if (!categorySelect || !name) return null;
                    var wanted = String(name).trim().toLowerCase();
                    var fallback = null;
                    for (var i = 0; i < categorySelect.options.length; i++) {
                        var option = categorySelect.options[i];
                        if (!option.value) continue;
                        var optionName = (option.getAttribute('data-category-name') || option.textContent || '').trim().toLowerCase();
                        if (optionName === wanted) {
                            return option;
                        }
                        if (!fallback && (optionName.indexOf(wanted) !== -1 || wanted.indexOf(optionName) !== -1)) {
                            fallback = option;
                        }
                    }
                    return fallback;
                }

                function applyCategoryFromModel(model) {
                    if (autoCategoryLocked || !categorySelect) return;
                    if (!model) {
                        categorySelect.value = '';
                        refreshSelect(categorySelect);
                        return;
                    }
                    var name = modelCategories[model];
                    if (!name) return;
                    var option = findCategoryOption(name);
                    if (!option) return;
                    if (categorySelect.value === option.value) return;
                    categorySelect.value = option.value;
                    categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
                    refreshSelect(categorySelect);
                }

                function setSpecField(name, value) {
                    var field = document.querySelector('[data-vehicle-spec="' + name + '"], [name="' + name + '"]');
                    if (!field || value === undefined || value === null || value === '') return;
                    var next = String(value);
                    if ((field.value || '') === next) {
                        lastAutoSpecs[name] = next;
                        return;
                    }
                    field.value = next;
                    lastAutoSpecs[name] = next;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                    if (field.tagName === 'SELECT') {
                        refreshSelect(field);
                    }
                }

                function applySpecsFromModel(model) {
                    if (autoSpecsLocked) return;
                    if (!model) {
                        lastAutoSpecs = {};
                        return;
                    }
                    var specs = modelSpecs[model];
                    if (!specs) return;
                    setSpecField('vehicle_type', specs.vehicle_type);
                    setSpecField('seats', specs.seats);
                    setSpecField('transmission', specs.transmission);
                    setSpecField('fuel_type', specs.fuel_type);
                    setSpecField('required_license_category', specs.required_license_category);
                }

                function fillModels(models, preferred) {
                    if (!modelSelect) return;

                    models = Array.isArray(models) ? models.slice() : [];
                    if (preferred && models.indexOf(preferred) === -1) {
                        models = models.concat([preferred]).sort(function (a, b) {
                            return a.localeCompare(b);
                        });
                    }

                    modelSelect.innerHTML = '';
                    var placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = '—';
                    modelSelect.appendChild(placeholder);

                    models.forEach(function (model) {
                        var option = document.createElement('option');
                        option.value = model;
                        option.textContent = model;
                        if (preferred && preferred === model) {
                            option.selected = true;
                        }
                        modelSelect.appendChild(option);
                    });

                    modelSelect.disabled = !makeSelect || !makeSelect.value;
                    if (!makeSelect || !makeSelect.value) {
                        modelSelect.value = '';
                    }

                    refreshSelect(modelSelect);
                    applyCategoryFromModel(modelSelect.value);
                    applySpecsFromModel(modelSelect.value);
                }

                function setModelLoading(loading) {
                    if (!modelSelect) return;
                    if (window.SearchableSelect && typeof window.SearchableSelect.setLoading === 'function') {
                        window.SearchableSelect.setLoading(modelSelect, loading);
                    } else {
                        var wrap = modelSelect.closest('[data-enhance-select]');
                        var spinner = wrap && wrap.querySelector('[data-select-spinner]');
                        if (wrap) wrap.classList.toggle('is-select-loading', !!loading);
                        if (spinner) {
                            spinner.hidden = !loading;
                            spinner.classList.toggle('hidden', !loading);
                            spinner.classList.toggle('flex', !!loading);
                        }
                        modelSelect.disabled = !!loading || !makeSelect || !makeSelect.value;
                    }
                    if (!loading) {
                        modelSelect.disabled = !makeSelect || !makeSelect.value;
                    }
                }

                function finishModels(requestId, models, categories, specs, preferred) {
                    if (requestId !== modelsRequest) return;
                    // Clear loading flags before rebuilding so the new UI is not stuck on spinner.
                    if (modelSelect) {
                        modelSelect.removeAttribute('data-loading');
                        modelSelect.removeAttribute('aria-busy');
                    }
                    modelCategories = categories || {};
                    modelSpecs = specs || {};
                    fillModels(models || [], preferred);
                    setModelLoading(false);
                }

                function loadModels(make, preferred) {
                    if (modelsAbort && typeof modelsAbort.abort === 'function') {
                        modelsAbort.abort();
                    }
                    modelsAbort = null;

                    if (!make) {
                        modelCategories = {};
                        modelSpecs = {};
                        setModelLoading(false);
                        fillModels([], null);
                        return;
                    }

                    var requestId = ++modelsRequest;
                    setModelLoading(true);

                    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
                    modelsAbort = controller;
                    var timedOut = false;
                    var timeoutId = setTimeout(function () {
                        timedOut = true;
                        if (controller) controller.abort();
                    }, modelsFetchTimeoutMs);

                    fetch(modelsUrl + '?make=' + encodeURIComponent(make), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        signal: controller ? controller.signal : undefined
                    })
                        .then(function (response) {
                            if (!response.ok) throw new Error('models_http_' + response.status);
                            return response.json();
                        })
                        .then(function (payload) {
                            clearTimeout(timeoutId);
                            if (requestId !== modelsRequest) return;
                            finishModels(
                                requestId,
                                payload.models || [],
                                payload.model_categories || {},
                                payload.model_specs || {},
                                preferred
                            );
                        })
                        .catch(function () {
                            clearTimeout(timeoutId);
                            if (requestId !== modelsRequest) return;
                            // Timed-out / failed requests must leave the select usable.
                            finishModels(requestId, preferred ? [preferred] : [], {}, {}, preferred);
                            if (timedOut) {
                                // no-op: spinner already cleared; user can still pick from fallback
                            }
                        });
                }

                if (categorySelect && priceInput) {
                    lastSuggested = suggestedPrice();
                    if (lastSuggested !== null) {
                        lastSuggested = String(Number(lastSuggested).toFixed(0));
                    }
                    categorySelect.addEventListener('change', function () {
                        var next = suggestedPrice();
                        var nextRaw = next !== null ? String(Number(next).toFixed(0)) : null;
                        var current = (priceInput.value || '').replace(/,/g, '').trim();
                        if (nextRaw !== null && (current === '' || current === lastSuggested)) {
                            var formatted = (window.CarRentalFormUi && window.CarRentalFormUi.formatAmountValue)
                                ? window.CarRentalFormUi.formatAmountValue(nextRaw)
                                : nextRaw;
                            priceInput.value = formatted;
                            priceInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        lastSuggested = nextRaw;
                    });
                }

                if (makeSelect && modelSelect) {
                    if (makeSelect.value) {
                        loadModels(makeSelect.value, selectedModel);
                    } else {
                        fillModels([], null);
                    }
                    makeSelect.addEventListener('change', function () {
                        autoCategoryLocked = false;
                        autoSpecsLocked = false;
                        modelCategories = {};
                        modelSpecs = {};
                        lastAutoSpecs = {};
                        if (categorySelect) {
                            categorySelect.value = '';
                            refreshSelect(categorySelect);
                        }
                        loadModels(makeSelect.value, null);
                    });
                    modelSelect.addEventListener('change', function () {
                        autoCategoryLocked = false;
                        autoSpecsLocked = false;
                        applyCategoryFromModel(modelSelect.value);
                        applySpecsFromModel(modelSelect.value);
                    });
                }
            })();
        </script>
    @endpush
@endonce
