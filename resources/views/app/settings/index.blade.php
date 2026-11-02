@php
    $brandColor = old('brand_color', $setting->brandColor());
    $secondaryColor = old('secondary_color', $brand->secondaryColor);
@endphp

<x-layouts.app :title="__('body.branding')">
    <x-common.page-header :title="__('body.branding')" :description="__('body.branding_description')" />

    <nav class="flex flex-wrap gap-1.5 mb-6">
        @foreach ($tabs as $item)
            <a href="{{ route('settings.index', ['tab' => $item]) }}"
               class="inline-flex items-center rounded-full px-3.5 py-1.5 text-sm font-medium border transition-colors {{ $tab === $item ? 'bg-brand text-white border-brand' : 'bg-card text-body border-line hover:border-brand hover:text-brand' }}">
                {{ __('body.brand_tab_'.$item) }}
            </a>
        @endforeach
    </nav>

    @if ($draft)
        <div class="mb-6 rounded-xl border border-warning/40 bg-warning/10 p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-ink">{{ __('body.theme_draft_title', ['number' => $draft->number]) }}</p>
                <p class="text-xs text-muted">{{ __('body.theme_draft_meta', ['when' => \App\Libraries\GeneralLibrary::formatDateTime($draft->updated_at), 'who' => $draft->author?->name ?? '—']) }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @unless ($previewing)
                    <form action="{{ route('settings.theme.preview') }}" method="POST">
                        @csrf
                        <x-common.button type="submit" variant="secondary" size="sm">{{ __('body.theme_preview') }}</x-common.button>
                    </form>
                @endunless
                @if (\App\Helpers\AuthHelper::can('approve settings.setting'))
                    <form action="{{ route('settings.theme.publish') }}" method="POST">
                        @csrf
                        <x-common.button type="submit" size="sm">{{ __('body.theme_publish') }}</x-common.button>
                    </form>
                @endif
                <form action="{{ route('settings.theme.discard') }}" method="POST">
                    @csrf
                    <x-common.button type="submit" variant="ghost" size="sm" data-confirm data-confirm-title="{{ __('body.theme_discard_confirm') }}" data-confirm-variant="danger">{{ __('body.theme_discard') }}</x-common.button>
                </form>
            </div>
        </div>
    @endif

    @if ($tab === 'history')
        <div class="bg-card border border-line rounded-xl overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-line flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <p class="text-sm text-muted">{{ __('body.theme_history_hint') }}</p>
                <form action="{{ route('settings.theme.reset') }}" method="POST" class="shrink-0">
                    @csrf
                    <x-common.button type="submit" variant="secondary" size="sm" data-confirm data-confirm-title="{{ __('body.theme_reset_confirm') }}">{{ __('body.theme_reset_button') }}</x-common.button>
                </form>
            </div>
            @forelse ($versions as $version)
                <div class="px-4 sm:px-6 py-3 border-b border-line last:border-0 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink">
                            {{ __('body.theme_version', ['number' => $version->number]) }}
                            <x-common.badge :color="['published' => 'success', 'draft' => 'warning'][$version->status] ?? 'muted'" class="ml-1">{{ __('body.theme_status_'.$version->status) }}</x-common.badge>
                        </p>
                        <p class="text-xs text-muted">
                            @if ($version->published_at)
                                {{ __('body.theme_published_meta', ['when' => \App\Libraries\GeneralLibrary::formatDateTime($version->published_at), 'who' => $version->publisher?->name ?? '—']) }}
                            @else
                                {{ __('body.theme_draft_meta', ['when' => \App\Libraries\GeneralLibrary::formatDateTime($version->updated_at), 'who' => $version->author?->name ?? '—']) }}
                            @endif
                        </p>
                    </div>
                    @if ($version->status !== 'draft')
                        <form action="{{ route('settings.theme.restore', $version) }}" method="POST" class="shrink-0">
                            @csrf
                            <x-common.button type="submit" variant="secondary" size="sm">{{ __('body.theme_restore') }}</x-common.button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="p-6 text-sm text-muted">{{ __('body.theme_history_empty') }}</p>
            @endforelse
        </div>
    @else
    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="section" value="{{ $tab }}">

        @if ($tab === 'details')
            <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-5">
                <p class="text-sm text-muted">{{ __('body.brand_details_hint') }}</p>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <x-common.input name="name" :label="__('body.company_name')" :value="old('name', $setting->name)" :hint="__('body.company_name_hint')" required />
                    <x-common.input name="legal_name" :label="__('body.brand_legal_name')" :value="old('legal_name', $brand->legalName)" :hint="__('body.brand_legal_name_hint')" />
                    <x-common.input name="tax_id" :label="__('body.brand_tax_id')" :value="old('tax_id', $brand->taxId)" />
                    <x-common.input name="registration_no" :label="__('body.brand_registration_no')" :value="old('registration_no', $brand->registrationNo)" />
                    <x-common.input type="email" name="email" :label="__('body.business_email')" :value="old('email', $setting->email)" :hint="__('body.business_email_hint')" />
                    <x-common.input name="phone" :label="__('body.brand_phone')" :value="old('phone', $brand->phone)" />
                    <x-common.input name="website" :label="__('body.brand_website')" :value="old('website', $brand->website)" placeholder="www.example.com" />
                </div>
                <x-common.textarea name="address" :label="__('body.brand_address')" :value="old('address', $brand->address)" :rows="3" />
            </div>
        @endif

        @if ($tab === 'look')
            <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-5">
                <p class="text-sm text-muted">{{ __('body.brand_look_hint') }}</p>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    @foreach ([['brand_color', $brandColor, __('body.brand_color'), null, true], ['secondary_color', $secondaryColor, __('body.brand_secondary_color'), __('body.brand_secondary_color_hint'), false]] as [$field, $value, $label, $hint, $required])
                        <div class="flex min-w-0 flex-col gap-2 w-full">
                            <label for="{{ $field }}" class="text-sm font-semibold text-ink">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
                            <div class="flex items-center gap-3">
                                <input type="color" value="{{ strtolower($value) }}" aria-label="{{ $label }}"
                                       oninput="document.getElementById('{{ $field }}').value = this.value.toUpperCase()"
                                       class="h-12 w-16 shrink-0 rounded-xl border border-line bg-card p-1 cursor-pointer shadow-sm">
                                <input type="text" name="{{ $field }}" id="{{ $field }}" value="{{ $value }}" maxlength="7" @required($required)
                                       class="flex-1 min-w-0 h-12 rounded-xl border px-4 text-sm bg-card shadow-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand uppercase @error($field) border-danger @else border-line @enderror">
                            </div>
                            @if ($hint)<p class="text-xs text-muted">{{ $hint }}</p>@endif
                            @error($field)<p class="text-xs text-danger" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endforeach

                    <div class="space-y-2">
                        <x-common.select name="font" id="brand-font" :label="__('body.brand_font')" :value="old('font', $brand->font)" :options="$fonts" hide-placeholder :hint="__('body.brand_font_hint')" />
                        <style>{!! \App\Support\Branding\Fonts::previewCss() !!}</style>
                        <div id="brand-font-preview" class="rounded-xl border border-line bg-surface px-4 py-3" style="font-family: {{ \App\Support\Branding\Fonts::previewStacks()[$brand->font] }}">
                            <p class="text-lg font-bold text-ink">{{ $setting->companyName() }}</p>
                            <p class="text-sm text-body">{{ __('body.brand_font_sample') }}</p>
                        </div>
                        <script>
                            (() => {
                                const stacks = @json(\App\Support\Branding\Fonts::previewStacks());
                                document.getElementById('brand-font').addEventListener('change', (event) => {
                                    document.getElementById('brand-font-preview').style.fontFamily = stacks[event.target.value] || '';
                                });
                            })();
                        </script>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <x-common.file-input name="logo" :label="__('body.web_logo')" accept="image/*" :max-size-mb="4" :existing-url="$setting->logoUrl()" />
                    <div class="space-y-2">
                        <x-common.file-input name="logo_dark" :label="__('body.brand_logo_dark')" accept="image/*" :max-size-mb="4"
                            :existing-url="$brand->logoDarkPath ? '/storage/'.$brand->logoDarkPath : null" :hint="__('body.brand_logo_dark_hint')" />
                        @if ($brand->logoDarkPath)
                            <x-common.checkbox name="remove_logo_dark" value="1" inline :label="__('body.brand_remove')" />
                        @endif
                    </div>
                    <x-common.file-input name="favicon" :label="__('body.favicon')" accept="image/*" :max-size-mb="1" :existing-url="$setting->faviconUrl()" />
                </div>
            </div>
        @endif

        @if ($tab === 'theme')
            <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-6">
                <div class="space-y-1">
                    <p class="text-sm text-muted">{{ __('body.theme_hint') }}</p>
                    <p class="text-xs text-muted">
                        @if ($live)
                            {{ __('body.theme_live', ['number' => $live->number, 'when' => \App\Libraries\GeneralLibrary::formatDateTime($live->published_at), 'who' => $live->publisher?->name ?? '—']) }}
                        @else
                            {{ __('body.theme_live_platform') }}
                        @endif
                    </p>
                </div>
                @foreach (['theme_group_brand' => ['accent', 'link'], 'theme_group_surfaces' => ['page', 'card', 'surface', 'line'], 'theme_group_text' => ['ink', 'body', 'muted'], 'theme_group_navigation' => ['sidebar', 'sidebar_ink', 'header', 'header_ink'], 'theme_group_status' => ['success', 'warning', 'danger', 'info']] as $group => $keys)
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-ink">{{ __('body.'.$group) }}</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                            @foreach ($keys as $key)
                                <x-common.color-field :name="'colors['.$key.']'" :label="__('body.theme_color_'.$key)"
                                    :value="old('colors.'.$key, ($draft ?? $live)?->payload['colors'][$key] ?? null)" :default="$themeDefaults[$key]" />
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
                <p class="text-xs text-muted">{{ __('body.theme_blank_hint') }}</p>
            </div>
        @endif

        @if ($tab === 'stamp')
            <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-5">
                <p class="text-sm text-muted">{{ __('body.brand_stamp_hint') }}</p>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    @foreach (['stamp' => $brand->stampPath, 'signature' => $brand->signaturePath] as $kind => $path)
                        <div class="space-y-2">
                            <x-common.file-input :name="$kind" :label="__('body.brand_'.$kind)" accept="image/png,image/jpeg" :max-size-mb="2"
                                :existing-url="$path ? route('settings.image', $kind) : null" :hint="__('body.brand_image_hint')" />
                            @if ($path)
                                <x-common.checkbox :name="'remove_'.$kind" value="1" inline :label="__('body.brand_remove')" />
                            @endif
                        </div>
                    @endforeach
                    <x-common.input name="signatory_name" :label="__('body.brand_signatory_name')" :value="old('signatory_name', $brand->signatoryName)" />
                    <x-common.input name="signatory_title" :label="__('body.brand_signatory_title')" :value="old('signatory_title', $brand->signatoryTitle)" placeholder="Managing Director" />
                </div>
            </div>
        @endif

        @if ($tab === 'payments')
            @php
                $methods = old('methods', $brand->paymentMethods);
                $methods = array_pad(array_values($methods), \App\Services\Branding\BrandingService::MAX_PAYMENT_METHODS, []);
            @endphp
            <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-5">
                <p class="text-sm text-muted">{{ __('body.brand_payments_hint', ['max' => \App\Services\Branding\BrandingService::MAX_PAYMENT_METHODS]) }} {{ __('body.brand_payment_empty_hint') }}</p>
                @foreach ($methods as $i => $method)
                    <div class="rounded-xl border border-line p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                        <x-common.select :name="'methods['.$i.'][type]'" :label="__('body.brand_payment_type')" :value="$method['type'] ?? 'bank'" hide-placeholder compact
                            :options="['bank' => __('body.brand_payment_bank'), 'mobile' => __('body.brand_payment_mobile')]" />
                        <x-common.input :name="'methods['.$i.'][label]'" :label="__('body.brand_payment_label')" :value="$method['label'] ?? ''" :placeholder="__('body.brand_payment_label_hint')" compact />
                        <x-common.input :name="'methods['.$i.'][provider]'" :label="__('body.brand_payment_provider')" :value="$method['provider'] ?? ''" compact />
                        <x-common.input :name="'methods['.$i.'][number]'" :label="__('body.brand_payment_number')" :value="$method['number'] ?? ''" compact />
                        <x-common.input :name="'methods['.$i.'][account_name]'" :label="__('body.brand_payment_account_name')" :value="$method['account_name'] ?? ''" compact />
                        <x-common.input :name="'methods['.$i.'][branch]'" :label="__('body.brand_payment_branch')" :value="$method['branch'] ?? ''" compact />
                        <x-common.input :name="'methods['.$i.'][swift]'" :label="__('body.brand_payment_swift')" :value="$method['swift'] ?? ''" compact />
                    </div>
                @endforeach
            </div>
        @endif

        @if ($tab === 'documents')
            @php
                $types = old('types', $design->types);
                $choice = fn (string $name, string $value, string $current) => 'rounded-xl border px-3 py-2 text-sm cursor-pointer select-none '.($current === $value ? 'border-brand bg-brand-light text-brand-dark font-semibold' : 'border-line bg-card hover:border-brand');
            @endphp
            <div class="grid grid-cols-1 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] gap-6 items-start" id="print-designer">
                <div class="space-y-6">
                    <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-5">
                        <p class="text-sm text-muted">{{ __('body.design_hint') }}</p>

                        <div>
                            <p class="text-sm font-semibold text-ink mb-2">{{ __('body.design_layout') }}</p>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach (\App\Support\Printing\PrintDesign::LAYOUTS as $layout)
                                    <label class="{{ $choice('layout', $layout, $design->layout) }}">
                                        <input type="radio" name="layout" value="{{ $layout }}" class="sr-only" @checked($design->layout === $layout)
                                               onchange="this.closest('div').querySelectorAll('label').forEach(l => l.classList.remove('border-brand','bg-brand-light','text-brand-dark','font-semibold')); this.parentElement.classList.add('border-brand','bg-brand-light','text-brand-dark','font-semibold')">
                                        <span class="block font-semibold">{{ __('body.design_layout_'.$layout) }}</span>
                                        <span class="block text-xs text-muted">{{ __('body.design_layout_'.$layout.'_hint') }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="flex flex-col gap-2">
                                <label for="accent" class="text-sm font-semibold text-ink">{{ __('body.design_accent') }}</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" value="{{ strtolower($design->accent) }}" aria-label="{{ __('body.design_accent') }}"
                                           oninput="document.getElementById('accent').value = this.value.toUpperCase(); document.getElementById('accent').dispatchEvent(new Event('input', {bubbles: true}))"
                                           class="h-11 w-14 shrink-0 rounded-xl border border-line bg-card p-1 cursor-pointer">
                                    <input type="text" name="accent" id="accent" value="{{ $design->accent }}" maxlength="7" class="flex-1 min-w-0 h-11 rounded-xl border border-line px-3 text-sm uppercase">
                                </div>
                                <p class="text-xs text-muted">{{ __('body.design_accent_hint') }}</p>
                            </div>
                            <x-common.select name="font" :label="__('body.design_font')" :value="$printFont" :options="$fonts" :placeholder="__('body.design_font_same')" />
                            <x-common.select name="paper" :label="__('body.design_paper')" :value="$design->paper" :options="\App\Support\Printing\PrintDesign::PAPERS" hide-placeholder />
                            <x-common.select name="logo_position" :label="__('body.design_logo_position')" :value="$design->logoPosition" hide-placeholder
                                :options="collect(\App\Support\Printing\PrintDesign::LOGO_POSITIONS)->mapWithKeys(fn ($p) => [$p => __('body.design_position_'.$p)])->all()" />
                            <x-common.select name="logo_size" :label="__('body.design_logo_size')" :value="$design->logoSize" hide-placeholder
                                :options="collect(\App\Support\Printing\PrintDesign::LOGO_SIZES)->keys()->mapWithKeys(fn ($s) => [$s => __('body.design_size_'.$s)])->all()" />
                        </div>

                        <x-common.input name="header_note" :label="__('body.design_header_note')" :value="$design->headerNote" :hint="__('body.design_header_note_hint')" />
                        <x-common.textarea name="footer_text" :label="__('body.design_footer')" :value="$design->footerText" :rows="2" :hint="__('body.design_footer_hint')" />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach (\App\Support\Printing\PrintDesign::SWITCHES as $switch => $default)
                                <x-common.checkbox :name="$switch" value="1" :checked="$design->{\Illuminate\Support\Str::camel($switch)}" :label="__('body.design_'.$switch)" />
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-4">
                        <div>
                            <p class="font-semibold text-ink">{{ __('body.design_per_type') }}</p>
                            <p class="text-sm text-muted">{{ __('body.design_per_type_hint') }}</p>
                        </div>
                        @foreach ($documentTypes as $type => $label)
                            <details class="rounded-xl border border-line p-3" @if (! empty($types[$type]['terms']) || ! empty($types[$type]['footer'])) open @endif>
                                <summary class="cursor-pointer text-sm font-semibold text-ink">{{ $label }}</summary>
                                <div class="mt-3 space-y-3">
                                    <x-common.textarea :name="'types['.$type.'][terms]'" :label="__('body.design_terms')" :value="$types[$type]['terms'] ?? ''" :rows="2" />
                                    <x-common.input :name="'types['.$type.'][footer]'" :label="__('body.design_type_footer')" :value="$types[$type]['footer'] ?? ''" :hint="__('body.design_type_footer_hint')" compact />
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>

                <div class="2xl:sticky 2xl:top-4 bg-card border border-line rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-line flex items-center justify-between gap-3">
                        <p class="font-semibold text-ink text-sm">{{ __('body.design_preview') }}</p>
                        <a id="print-preview-pdf" href="{{ route('settings.print-preview', ['format' => 'pdf']) }}" target="_blank" class="text-sm font-semibold text-brand hover:text-brand-dark">{{ __('body.design_preview_pdf') }}</a>
                    </div>
                    <iframe id="print-preview" title="{{ __('body.design_preview') }}" src="{{ route('settings.print-preview') }}" class="w-full bg-line" style="height: 900px; border: 0"></iframe>
                </div>
            </div>

            <script>
                (function () {
                    var form = document.getElementById('print-designer').closest('form');
                    var frame = document.getElementById('print-preview');
                    var pdf = document.getElementById('print-preview-pdf');
                    var base = @json(route('settings.print-preview'));
                    var timer;
                    function query() {
                        var params = new URLSearchParams();
                        new FormData(form).forEach(function (value, key) {
                            if (key !== '_token' && key !== '_method' && key !== 'section') params.append(key, value);
                        });
                        return params.toString();
                    }
                    function refresh() {
                        clearTimeout(timer);
                        timer = setTimeout(function () {
                            var q = query();
                            frame.src = base + '?' + q;
                            pdf.href = base + '?format=pdf&' + q;
                        }, 400);
                    }
                    form.addEventListener('input', refresh);
                    form.addEventListener('change', refresh);
                })();
            </script>
        @endif

        <div class="flex [&>*]:w-full sm:[&>*]:w-auto sm:justify-end">
            <x-common.button type="submit">{{ $tab === 'theme' ? __('body.theme_save_draft') : __('body.save_changes') }}</x-common.button>
        </div>
    </form>
    @endif
</x-layouts.app>
