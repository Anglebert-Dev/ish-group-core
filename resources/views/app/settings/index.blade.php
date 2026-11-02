@php
    $brandColor = old('brand_color', $setting->brandColor());
    $secondaryColor = old('secondary_color', $brand->secondaryColor);
@endphp

<x-layouts.app :title="__('body.branding')">
    <x-common.page-header :title="__('body.branding')" :description="__('body.branding_description')" />

    <nav class="flex flex-wrap gap-1.5 mb-6">
        @foreach ($tabs as $item)
            <a href="{{ route('settings.index', ['tab' => $item]) }}"
               class="inline-flex items-center rounded-full px-3.5 py-1.5 text-sm font-medium border transition-colors {{ $tab === $item ? 'bg-brand text-on-brand border-brand' : 'bg-card text-body border-line hover:border-brand hover:text-brand' }}">
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
                    <x-common.input name="short_name" :label="__('body.brand_short_name')" :value="old('short_name', $details['short_name'])" :hint="__('body.brand_short_name_hint')" maxlength="30" />
                    <x-common.input name="slogan" :label="__('body.brand_slogan')" :value="old('slogan', $details['slogan'])" :hint="__('body.brand_slogan_hint')" maxlength="120" />
                    <x-common.select name="locale" :label="__('body.brand_locale')" :value="old('locale', $details['locale'] ?? config('app.locale'))" :options="\App\Support\Locale\Locales::available()" hide-placeholder :hint="__('body.brand_locale_hint')" />
                    <x-common.input name="tax_id" :label="__('body.brand_tax_id')" :value="old('tax_id', $brand->taxId)" />
                    <x-common.input name="registration_no" :label="__('body.brand_registration_no')" :value="old('registration_no', $brand->registrationNo)" />
                    <x-common.input type="email" name="email" :label="__('body.business_email')" :value="old('email', $setting->email)" :hint="__('body.business_email_hint')" />
                    <x-common.input name="phone" :label="__('body.brand_phone')" :value="old('phone', $brand->phone)" />
                    <x-common.input name="website" :label="__('body.brand_website')" :value="old('website', $brand->website)" placeholder="www.example.com" />
                </div>
                <x-common.textarea name="address" :label="__('body.brand_address')" :value="old('address', $brand->address)" :rows="3" />
                <x-common.textarea name="description" :label="__('body.brand_description')" :value="old('description', $details['description'])" :rows="3" :hint="__('body.brand_description_hint')" />
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <x-common.select name="date_format" :label="__('body.brand_date_format')" :value="old('date_format', $details['date_format'] ?? 'd/m/Y')" hide-placeholder
                        :options="collect(\App\Services\Branding\BrandingService::DATE_FORMATS)->mapWithKeys(fn ($f) => [$f => now()->setDate((int) now()->format('Y'), 12, 31)->format($f)])->all()" />
                    <x-common.select name="number_format" :label="__('body.brand_number_format')" :value="old('number_format', $details['number_format'] ?? 'comma_dot')" hide-placeholder
                        :options="collect(\App\Services\Branding\BrandingService::NUMBER_FORMATS)->map(fn ($s) => number_format(1234567.89, 2, $s[0], $s[1]))->all()" />
                </div>
                <fieldset class="space-y-4 pt-2">
                    <legend class="text-sm font-bold text-ink">{{ __('body.brand_support') }}</legend>
                    <p class="text-xs text-muted">{{ __('body.brand_support_hint') }}</p>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                        <x-common.input type="email" name="support_email" :label="__('body.brand_support_email')" :value="old('support_email', $details['support_email'])" />
                        <x-common.input name="support_phone" :label="__('body.brand_support_phone')" :value="old('support_phone', $details['support_phone'])" />
                        <x-common.input type="url" name="help_url" :label="__('body.brand_help_url')" :value="old('help_url', $details['help_url'])" placeholder="https://" />
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        <x-common.input name="app_footer" :label="__('body.brand_app_footer')" :value="old('app_footer', $details['app_footer'])" maxlength="300" />
                        <x-common.input name="copyright" :label="__('body.brand_copyright')" :value="old('copyright', $details['copyright'])" maxlength="160" :placeholder="'© '.date('Y').' '.$setting->companyName()" />
                    </div>
                </fieldset>
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
                            @if ($field === 'brand_color')
                                {{-- How buttons really look on screen, per mode (the brand is fitted when it would vanish). --}}
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    @foreach ($brandOnScreen as $mode => $colors)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border px-2 py-1" style="background: {{ $colors['card'] }}; border-color: {{ $colors['line'] }}">
                                            <span class="rounded-md px-2 py-0.5 font-semibold" style="background: {{ $colors['brand'] }}; color: {{ $colors['on_brand'] }}">{{ __('body.theme_mode_'.$mode) }}</span>
                                            <code style="color: {{ $colors['muted'] }}">{{ $colors['brand'] }}</code>
                                        </span>
                                    @endforeach
                                </div>
                                <p class="text-xs text-muted">{{ __('body.brand_on_screen_hint') }}</p>
                            @endif
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
                    @foreach (['logo_login' => 4, 'logo_mobile' => 2] as $field => $size)
                        <div class="space-y-2">
                            <x-common.file-input :name="$field" :label="__('body.brand_'.$field)" accept="image/*" :max-size-mb="$size" :existing-url="$extraLogos[$field]" :hint="__('body.brand_'.$field.'_hint')" />
                            @if ($extraLogos[$field])
                                <x-common.checkbox :name="'remove_'.$field" value="1" inline :label="__('body.brand_remove')" />
                            @endif
                        </div>
                    @endforeach
                    <x-common.file-input name="favicon" :label="__('body.favicon')" accept="image/*" :max-size-mb="1" :existing-url="$setting->faviconUrl()" />
                </div>
            </div>
        @endif

        @if (in_array($tab, ['theme', 'layout', 'dashboard', 'menu', 'advanced'], true))
            <div class="grid gap-6 2xl:grid-cols-2 items-start">
                <div class="min-w-0">
        @endif

        @if ($tab === 'theme')
            @php
                $saved = ($draft ?? $live)?->payload ?? [];
                $groups = ['theme_group_brand' => ['accent', 'link'], 'theme_group_surfaces' => ['page', 'card', 'surface', 'line'], 'theme_group_text' => ['ink', 'body', 'muted'], 'theme_group_navigation' => ['sidebar', 'sidebar_ink', 'header', 'header_ink'], 'theme_group_status' => ['success', 'warning', 'danger', 'info'], 'theme_group_chart' => ['chart_1', 'chart_2', 'chart_3', 'chart_4', 'chart_5', 'chart_6']];
                $currentMode = old('mode', $theme['mode'] ?? \App\Support\Branding\ThemeSchema::DEFAULT_MODE);
            @endphp
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

                <fieldset class="space-y-3">
                    <legend class="text-sm font-bold text-ink">{{ __('body.theme_mode') }}</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach (\App\Support\Branding\ThemeTokens::MODES as $mode)
                            <label class="rounded-xl border px-4 py-3 cursor-pointer has-[:checked]:border-brand has-[:checked]:bg-brand-light border-line">
                                <input type="radio" name="mode" value="{{ $mode }}" class="sr-only" @checked($currentMode === $mode)>
                                <span class="block text-sm font-semibold text-ink">{{ __('body.theme_mode_'.$mode) }}</span>
                                <span class="block text-xs text-muted mt-0.5">{{ __('body.theme_mode_'.$mode.'_hint') }}</span>
                            </label>
                        @endforeach
                    </div>
                    <input type="hidden" name="user_mode" value="0">
                    <x-common.checkbox name="user_mode" value="1" :checked="(bool) old('user_mode', $theme['user_mode'] ?? true)" :label="__('body.theme_user_mode')" :description="__('body.theme_user_mode_hint')" />
                </fieldset>

                @if ($contrast)
                    <div class="rounded-xl border {{ collect($contrast)->contains('blocking', true) ? 'border-danger/30 bg-danger/10' : 'border-warning/30 bg-warning/10' }} p-4 space-y-2" id="theme-contrast">
                        <p class="text-sm font-semibold text-ink">{{ collect($contrast)->contains('blocking', true) ? __('body.theme_contrast_blocking') : __('body.theme_contrast_warnings') }}</p>
                        <ul class="space-y-1.5">
                            @foreach ($contrast as $issue)
                                <li class="text-sm text-body flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <x-common.badge :color="$issue['blocking'] ? 'danger' : 'warning'">{{ __('body.theme_mode_'.$issue['mode']) }}</x-common.badge>
                                    <span>{{ __('body.theme_contrast_issue', ['text' => __('body.theme_color_'.$issue['text']), 'background' => __('body.theme_color_'.$issue['background']), 'ratio' => $issue['ratio'], 'min' => $issue['min']]) }}</span>
                                    <span class="inline-flex items-center gap-1.5 text-xs text-muted">
                                        {{ __('body.theme_contrast_try') }}
                                        <span class="inline-block h-3.5 w-3.5 rounded border border-line" style="background: {{ $issue['suggestion'] }}"></span>
                                        <code>{{ $issue['suggestion'] }}</code>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-2 rounded-xl border border-line p-4">
                    <p class="text-sm font-bold text-ink">{{ __('body.presets') }}</p>
                    <p class="text-xs text-muted">{{ __('body.presets_hint') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach (array_keys(\App\Support\Branding\ThemeSchema::PRESETS) as $preset)
                            <button type="submit" formaction="{{ route('settings.theme.presets.apply') }}" name="preset" value="builtin:{{ $preset }}" formnovalidate
                                    class="rounded-lg border border-line bg-card px-3 py-1.5 text-sm text-body hover:border-brand">{{ __('body.preset_'.$preset) }}</button>
                        @endforeach
                    </div>
                    @if ($savedPresets)
                        <p class="text-xs font-semibold text-muted uppercase tracking-wide pt-2">{{ __('body.presets_saved') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($savedPresets as $index => $saved)
                                <span class="inline-flex items-center rounded-lg border border-line bg-card overflow-hidden">
                                    <button type="submit" formaction="{{ route('settings.theme.presets.apply') }}" name="preset" value="saved:{{ $index }}" formnovalidate class="px-3 py-1.5 text-sm text-body hover:text-brand">{{ $saved['name'] }}</button>
                                    <button type="submit" formaction="{{ route('settings.theme.presets.delete') }}" name="preset_index" value="{{ $index }}" formnovalidate class="px-2 py-1.5 text-sm text-muted hover:text-danger border-l border-line" aria-label="{{ __('body.delete') }} {{ $saved['name'] }}">×</button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                    <div class="flex flex-wrap items-end gap-2 pt-2">
                        <x-common.input name="preset_name" :label="__('body.preset_save_as')" maxlength="60" class="flex-1 min-w-[12rem]" />
                        <x-common.button type="submit" variant="secondary" size="sm" formaction="{{ route('settings.theme.presets.save') }}" formnovalidate>{{ __('body.preset_save') }}</x-common.button>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-sm font-bold text-ink">{{ __('body.theme_palettes') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Support\Branding\ThemeSchema::PALETTES as $key => $palette)
                            @php $swatch = $palette + $themeDefaults; @endphp
                            <button type="button" data-palette='@json($palette)' class="inline-flex items-center gap-2 rounded-lg border border-line bg-card px-3 py-1.5 text-sm text-body hover:border-brand">
                                <span class="flex -space-x-1">
                                    @foreach (['sidebar', 'page', 'card', 'brand'] as $k)
                                        <span class="h-4 w-4 rounded-full border border-line" style="background: {{ $swatch[$k] }}"></span>
                                    @endforeach
                                </span>
                                {{ __('body.theme_palette_'.$key) }}
                            </button>
                        @endforeach
                    </div>
                    <p class="text-xs text-muted">{{ __('body.theme_palettes_hint') }}</p>
                </div>

                @foreach ($groups as $group => $keys)
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold text-ink">{{ __('body.'.$group) }}</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                            @foreach ($keys as $key)
                                <x-common.color-field :name="'colors['.$key.']'" :label="__('body.theme_color_'.$key)"
                                    :value="old('colors.'.$key, $saved['colors'][$key] ?? null)" :default="$themeDefaults[$key]" />
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <details class="rounded-xl border border-line p-4" @if (($saved['colors_dark'] ?? []) !== [] || $currentMode !== 'light') open @endif>
                    <summary class="cursor-pointer text-sm font-bold text-ink">{{ __('body.theme_dark_colors') }}</summary>
                    <p class="text-xs text-muted mt-2">{{ __('body.theme_dark_colors_hint') }}</p>
                    <div class="space-y-5 mt-4">
                        @foreach ($groups as $group => $keys)
                            <fieldset class="space-y-3">
                                <legend class="text-sm font-semibold text-ink">{{ __('body.'.$group) }}</legend>
                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                                    @foreach ($group === 'theme_group_brand' ? ['brand', ...$keys] : $keys as $key)
                                        <x-common.color-field :name="'colors_dark['.$key.']'" :label="__('body.theme_color_'.$key)"
                                            :value="old('colors_dark.'.$key, $saved['colors_dark'][$key] ?? null)" :default="$themeDarkDefaults[$key]" />
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                </details>

                <p class="text-xs text-muted">{{ __('body.theme_blank_hint') }}</p>
            </div>

            <script>
                document.querySelectorAll('[data-palette]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var palette = JSON.parse(button.dataset.palette);
                        document.querySelectorAll('input[type=text][name^="colors["]').forEach(function (input) {
                            var key = input.name.slice(7, -1);
                            input.value = palette[key] || '';
                            input.dispatchEvent(new Event('input', {bubbles: true}));
                        });
                    });
                });
            </script>
        @endif

        @if ($tab === 'layout')
            @php $themeLayout = new \App\Support\Branding\ThemeLayout($theme); @endphp
            <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-6">
                <p class="text-sm text-muted">{{ __('body.layout_hint') }}</p>
                @foreach (\App\Support\Branding\ThemeSchema::LAYOUT as $key => $options)
                    <fieldset class="space-y-2">
                        <legend class="text-sm font-bold text-ink">{{ __('body.layout_'.$key) }}</legend>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            @foreach ($options as $option)
                                <label class="rounded-xl border border-line px-4 py-2.5 cursor-pointer has-[:checked]:border-brand has-[:checked]:bg-brand-light">
                                    <input type="radio" name="layout[{{ $key }}]" value="{{ $option }}" class="sr-only" @checked(old('layout.'.$key, $themeLayout->get($key)) === $option)>
                                    <span class="block text-sm font-semibold text-ink">{{ __('body.layout_'.$key.'_'.$option) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <x-common.select name="heading_font" :label="__('body.layout_heading_font')" :value="old('heading_font', $themeLayout->headingFont() ?? 'same')"
                    :options="['same' => __('body.layout_heading_font_same')] + \App\Support\Branding\Fonts::options()" hide-placeholder :hint="__('body.layout_heading_font_hint')" />

                <fieldset class="space-y-2">
                    <legend class="text-sm font-bold text-ink">{{ __('body.layout_switches') }}</legend>
                    @foreach (array_keys(\App\Support\Branding\ThemeSchema::SWITCHES) as $switch)
                        <x-common.checkbox :name="'switches['.$switch.']'" value="1" :checked="(bool) old('switches.'.$switch, $themeLayout->on($switch))" :label="__('body.layout_switch_'.$switch)" />
                    @endforeach
                </fieldset>
            </div>
        @endif

        @if ($tab === 'login')
            @php $handle = old('login_handle', $loginPage['handle'] ?? $loginPage['suggested']); @endphp
            <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-5">
                <p class="text-sm text-muted">{{ __('body.login_hint') }}</p>
                <div class="rounded-xl border border-line bg-surface p-4 space-y-2">
                    <x-common.input name="login_handle" :label="__('body.login_handle')" :value="$handle" required maxlength="40" :hint="__('body.login_handle_hint')" />
                    @if ($loginPage['handle'])
                        <p class="text-sm text-body">{{ __('body.login_link') }}
                            <a href="{{ route('staff.business-login', $loginPage['handle']) }}" target="_blank" rel="noopener" class="font-semibold text-link break-all">{{ route('staff.business-login', $loginPage['handle']) }}</a>
                        </p>
                    @endif
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <x-common.input name="login_heading" :label="__('body.login_heading')" :value="old('login_heading', $loginPage['heading'])" :placeholder="__('body.auth_welcome_back')" maxlength="120" />
                    <x-common.input name="login_panel_text" :label="__('body.login_panel_text')" :value="old('login_panel_text', $loginPage['panel_text'])" :placeholder="__('body.auth_panel_text_staff')" maxlength="200" :hint="__('body.login_panel_text_hint')" />
                </div>
                <x-common.textarea name="login_welcome" :label="__('body.login_welcome')" :value="old('login_welcome', $loginPage['welcome'])" :rows="2" :placeholder="__('body.auth_login_subtitle_staff')" />
                <x-common.textarea name="login_footer" :label="__('body.login_footer')" :value="old('login_footer', $loginPage['footer'])" :rows="2" :hint="__('body.login_footer_hint')" />
                <input type="hidden" name="login_show_contact" value="0">
                <x-common.checkbox name="login_show_contact" value="1" :checked="(bool) old('login_show_contact', $loginPage['show_contact'])" :label="__('body.login_show_contact')" :description="__('body.login_show_contact_hint')" />
                <div class="space-y-2 max-w-md">
                    <x-common.file-input name="login_image" :label="__('body.login_image')" accept="image/*" :max-size-mb="4" :existing-url="$loginPage['image']" :hint="__('body.login_image_hint')" />
                    @if ($loginPage['image'])
                        <x-common.checkbox name="remove_login_image" value="1" inline :label="__('body.brand_remove')" />
                    @endif
                </div>
            </div>
        @endif

        @if ($tab === 'dashboard')
            @php
                $dash = $theme['dashboard'] ?? [];
                $ordered = array_values(array_unique([...array_intersect($dash['order'] ?? [], array_keys($dashboardBlocks)), ...array_keys($dashboardBlocks)]));
                $note = $theme['announcement'] ?? [];
            @endphp
            <div class="space-y-6">
                <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-5">
                    <p class="text-sm text-muted">{{ __('body.dash_hint') }}</p>
                    <x-common.input name="dashboard[welcome_title]" :label="__('body.dash_welcome_title')" :value="$dash['welcome_title'] ?? ''" maxlength="120" :hint="__('body.dash_welcome_title_hint')" />
                    <x-common.textarea name="dashboard[welcome_text]" :label="__('body.dash_welcome_text')" :value="$dash['welcome_text'] ?? ''" :rows="2" />
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-common.select name="dashboard[banner]" :label="__('body.dash_banner')" :value="$dash['banner'] ?? 'none'" hide-placeholder
                            :options="collect(\App\Support\Branding\ThemeSchema::BANNERS)->mapWithKeys(fn ($b) => [$b => __('body.dash_banner_'.$b)])->all()" :hint="__('body.dash_banner_hint')" />
                        <x-common.select name="dashboard[stat_columns]" :label="__('body.dash_stat_columns')" :value="(string) ($dash['stat_columns'] ?? 4)" hide-placeholder :options="['2' => '2', '3' => '3', '4' => '4']" />
                    </div>
                    <x-common.select name="landing" :label="__('body.dash_landing')" :value="$theme['landing'] ?? 'dashboard'" hide-placeholder
                        :options="$menuItems->pluck('label', 'route')->all()" :hint="__('body.dash_landing_hint')" />
                </div>

                <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-3">
                    <p class="text-sm font-bold text-ink">{{ __('body.dash_blocks') }}</p>
                    <p class="text-xs text-muted">{{ __('body.dash_blocks_hint') }}</p>
                    <ul id="dash-blocks" class="divide-y divide-line border border-line rounded-xl">
                        @foreach ($ordered as $key)
                            <li class="p-3 space-y-2" data-block>
                                <input type="hidden" name="dashboard[order][]" value="{{ $key }}">
                                <div class="flex items-center gap-2">
                                    <div class="flex flex-col">
                                        <button type="button" class="text-muted hover:text-ink leading-none" aria-label="{{ __('body.move_up') }}" onclick="var li = this.closest('li'); li.previousElementSibling && li.parentNode.insertBefore(li, li.previousElementSibling); li.closest('form').dispatchEvent(new Event('change'))">▲</button>
                                        <button type="button" class="text-muted hover:text-ink leading-none" aria-label="{{ __('body.move_down') }}" onclick="var li = this.closest('li'); li.nextElementSibling && li.parentNode.insertBefore(li.nextElementSibling, li); li.closest('form').dispatchEvent(new Event('change'))">▼</button>
                                    </div>
                                    <span class="flex-1 text-sm font-medium text-ink">{{ $dashboardBlocks[$key] }}</span>
                                    <x-common.checkbox name="dashboard[hidden][]" :value="$key" inline :checked="in_array($key, $dash['hidden'] ?? [], true)" :label="__('body.dash_hide')" />
                                </div>
                                @if ($roles->isNotEmpty())
                                    <details class="pl-7">
                                        <summary class="cursor-pointer text-xs text-muted">{{ __('body.dash_hide_for_roles') }}</summary>
                                        <div class="flex flex-wrap gap-3 mt-2">
                                            @foreach ($roles as $role)
                                                <x-common.checkbox :name="'dashboard[roles_hidden]['.$key.'][]'" :value="$role->id" inline :checked="in_array($role->id, $dash['roles_hidden'][$key] ?? [], true)" :label="$role->display_name" />
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-4">
                    <p class="text-sm font-bold text-ink">{{ __('body.dash_announcement') }}</p>
                    <p class="text-xs text-muted">{{ __('body.dash_announcement_hint') }}</p>
                    <x-common.textarea name="announcement[text]" :label="__('body.dash_announcement_text')" :value="$note['text'] ?? ''" :rows="2" />
                    <div class="grid sm:grid-cols-3 gap-4">
                        <x-common.select name="announcement[tone]" :label="__('body.dash_announcement_tone')" :value="$note['tone'] ?? 'info'" hide-placeholder
                            :options="collect(\App\Support\Branding\ThemeSchema::TONES)->mapWithKeys(fn ($t) => [$t => __('body.dash_tone_'.$t)])->all()" />
                        <x-common.input type="date" name="announcement[from]" :label="__('body.dash_announcement_from')" :value="$note['from'] ?? ''" />
                        <x-common.input type="date" name="announcement[until]" :label="__('body.dash_announcement_until')" :value="$note['until'] ?? ''" />
                    </div>
                </div>
            </div>
        @endif

        @if ($tab === 'menu')
            @php
                $menu = $theme['menu'] ?? [];
                $groupNames = $menuItems->pluck('group')->filter()->unique()->sort()->values();
            @endphp
            <div class="space-y-6">
                <div class="bg-card border border-line rounded-xl overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-line">
                        <p class="text-sm text-muted">{{ __('body.menu_hint') }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                                    <th class="px-4 py-3 font-semibold">{{ __('body.menu_item') }}</th>
                                    <th class="px-4 py-3 font-semibold">{{ __('body.menu_icon') }}</th>
                                    <th class="px-4 py-3 font-semibold">{{ __('body.menu_group') }}</th>
                                    <th class="px-4 py-3 font-semibold">{{ __('body.menu_order') }}</th>
                                    <th class="px-4 py-3 font-semibold">{{ __('body.dash_hide') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($menuItems as $item)
                                    @php $choice = $menu['items'][$item['route']] ?? []; $field = 'menu[items]['.$item['route'].']'; @endphp
                                    <tr>
                                        <td class="px-4 py-2 min-w-[12rem]">
                                            <input type="text" name="{{ $field }}[label]" value="{{ $choice['label'] ?? '' }}" placeholder="{{ $item['label'] }}" maxlength="60" class="w-full h-9 rounded-lg border border-line bg-card px-2 text-sm">
                                        </td>
                                        <td class="px-4 py-2">
                                            <select name="{{ $field }}[icon]" class="h-9 rounded-lg border border-line bg-card px-2 text-sm">
                                                <option value="">{{ __('body.menu_default') }}</option>
                                                @foreach (array_keys(\App\Support\Branding\ThemeSchema::ICONS) as $icon)
                                                    <option value="{{ $icon }}" @selected(($choice['icon'] ?? '') === $icon)>{{ __('body.menu_icon_'.$icon) }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-4 py-2">
                                            @if ($item['group'])
                                                <select name="{{ $field }}[group]" class="h-9 rounded-lg border border-line bg-card px-2 text-sm">
                                                    <option value="">{{ $item['group'] }}</option>
                                                    @foreach ($groupNames as $group)
                                                        @if ($group !== $item['group'])
                                                            <option value="{{ $group }}" @selected(($choice['group'] ?? '') === $group)>{{ $group }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            @else
                                                <span class="text-xs text-muted">{{ __('body.menu_top') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="number" name="{{ $field }}[order]" value="{{ $choice['order'] ?? '' }}" placeholder="{{ $item['order'] }}" min="-999" max="999" class="w-20 h-9 rounded-lg border border-line bg-card px-2 text-sm">
                                        </td>
                                        <td class="px-4 py-2">
                                            <x-common.checkbox :name="$field.'[hidden]'" value="1" inline :checked="! empty($choice['hidden'])" :label="__('body.dash_hide')" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-3">
                    <p class="text-sm font-bold text-ink">{{ __('body.menu_groups') }}</p>
                    @foreach ($groupNames as $group)
                        <div class="grid grid-cols-[minmax(0,1fr)_5.5rem] gap-3 items-end">
                            <x-common.input :name="'menu[groups]['.$group.'][label]'" :label="$group" :value="$menu['groups'][$group]['label'] ?? ''" :placeholder="$group" maxlength="60" />
                            <x-common.input type="number" :name="'menu[groups]['.$group.'][order]'" :label="__('body.menu_order')" :value="$menu['groups'][$group]['order'] ?? ''" min="-999" max="999" />
                        </div>
                    @endforeach
                    <p class="text-xs text-muted">{{ __('body.menu_groups_hint') }}</p>
                </div>
            </div>
        @endif

        @if ($tab === 'emails')
            <div class="grid gap-6 2xl:grid-cols-2 items-start">
                <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-5" id="email-designer">
                    <p class="text-sm text-muted">{{ __('body.emails_hint') }}</p>
                    <x-common.color-field name="email_header_color" :label="__('body.emails_header_color')" :value="old('email_header_color', $emailSettings['email_header_color'] ?? null)" :default="$setting->brandColor()" :hint="__('body.emails_header_color_hint')" />
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-common.input name="email_sender_name" :label="__('body.emails_sender_name')" :value="old('email_sender_name', $emailSettings['email_sender_name'] ?? '')" :placeholder="$setting->companyName()" maxlength="80" :hint="__('body.emails_sender_name_hint')" />
                        <x-common.input type="email" name="email_reply_to" :label="__('body.emails_reply_to')" :value="old('email_reply_to', $emailSettings['email_reply_to'] ?? '')" :placeholder="$setting->contactEmail()" :hint="__('body.emails_reply_to_hint')" />
                    </div>
                    <x-common.textarea name="email_signature" :label="__('body.emails_signature')" :value="old('email_signature', $emailSettings['email_signature'] ?? '')" :rows="3" :placeholder="__('body.emails_signature_placeholder')" />
                    <x-common.textarea name="email_footer" :label="__('body.emails_footer')" :value="old('email_footer', $emailSettings['email_footer'] ?? '')" :rows="2" :hint="__('body.emails_footer_hint')" />
                    <input type="hidden" name="email_show_contact" value="0">
                    <x-common.checkbox name="email_show_contact" value="1" :checked="(bool) old('email_show_contact', $emailSettings['email_show_contact'] ?? false)" :label="__('body.emails_show_contact')" />
                </div>
                <div class="2xl:sticky 2xl:top-4 bg-card border border-line rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-line"><p class="font-semibold text-ink text-sm">{{ __('body.studio_title') }}</p></div>
                    <iframe id="email-preview" title="{{ __('body.studio_title') }}" src="{{ route('settings.studio.email') }}" class="w-full bg-line" style="height: 640px; border: 0"></iframe>
                </div>
            </div>
            <script>
                (function () {
                    var box = document.getElementById('email-designer'), form = box.closest('form'), frame = document.getElementById('email-preview'), timer;
                    var base = @json(route('settings.studio.email'));
                    function refresh() {
                        clearTimeout(timer);
                        timer = setTimeout(function () {
                            var params = new URLSearchParams();
                            new FormData(form).forEach(function (value, name) { if (name.indexOf('email_') === 0) params.set('email[' + name.slice(6) + ']', value); });
                            frame.src = base + '?' + params.toString();
                        }, 400);
                    }
                    form.addEventListener('input', refresh);
                    form.addEventListener('change', refresh);
                })();
            </script>
        @endif

        @if ($tab === 'advanced')
            @php $locked = fn () => '<div class="rounded-xl border border-line bg-surface p-4 text-sm"><p class="font-semibold text-ink">'.e(__('body.not_in_plan')).'</p><p class="text-muted">'.e(__('body.not_in_plan_hint')).'</p></div>'; @endphp
            <div class="space-y-6">
                <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-3">
                    <p class="text-sm font-bold text-ink">{{ __('body.css_title') }}</p>
                    <p class="text-xs text-muted">{{ __('body.css_hint') }}</p>
                    @if (! $advanced['cssAllowed'])
                        {!! $locked() !!}
                    @elseif ($advanced['cssPlatformOff'])
                        <p class="text-sm text-warning">{{ __('body.css_platform_off') }}</p>
                    @else
                        <x-common.textarea name="custom_css" :label="__('body.css_label')" :value="old('custom_css', $advanced['css'])" :rows="10" class="font-mono" placeholder=".page-subtitle { font-style: italic; }" />
                    @endif
                </div>

                <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-3">
                    <p class="text-sm font-bold text-ink">{{ __('body.font_upload_title') }}</p>
                    <p class="text-xs text-muted">{{ __('body.font_upload_hint') }}</p>
                    @if (! $advanced['fontsAllowed'])
                        {!! $locked() !!}
                    @else
                        @if ($advanced['font'])
                            <p class="text-sm text-body">{{ __('body.font_current', ['name' => $advanced['font']['name']]) }} @if (! $advanced['font']['ttf']) <span class="text-muted">· {{ __('body.font_no_ttf') }}</span> @endif</p>
                            <x-common.checkbox name="remove_font" value="1" inline :label="__('body.brand_remove')" />
                        @endif
                        <x-common.input name="font_name" :label="__('body.font_name')" :value="old('font_name', $advanced['font']['name'] ?? '')" maxlength="40" />
                        <div class="grid sm:grid-cols-2 gap-4">
                            <x-common.input type="file" name="font_woff2" :label="__('body.font_woff2')" accept=".woff2" />
                            <x-common.input type="file" name="font_ttf" :label="__('body.font_ttf')" accept=".ttf" :hint="__('body.font_ttf_hint')" />
                        </div>
                        <x-common.checkbox name="font_licence" value="1" :label="__('body.font_licence')" />
                    @endif
                </div>

                <div class="bg-card border border-line rounded-xl p-4 sm:p-6 space-y-3">
                    <p class="text-sm font-bold text-ink">{{ __('body.words_title') }}</p>
                    <p class="text-xs text-muted">{{ __('body.words_hint') }}</p>
                    @if (! $advanced['wordsAllowed'])
                        {!! $locked() !!}
                    @else
                        <div class="grid sm:grid-cols-2 gap-3">
                            @foreach ($advanced['terms'] as $key => $word)
                                <x-common.input :name="'words['.$key.']'" :label="$word" :value="old('words.'.$key, $advanced['words'][$key] ?? '')" :placeholder="$word" maxlength="40" />
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if (in_array($tab, ['theme', 'layout', 'dashboard', 'menu', 'advanced'], true))
                </div>
                @include('app.settings.studio.panel')
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
                        <div class="grid sm:grid-cols-2 gap-4">
                            <x-common.input name="watermark_text" :label="__('body.design_watermark_text')" :value="$design->watermarkText" maxlength="30" :placeholder="__('body.design_watermark_placeholder')" :hint="__('body.design_watermark_text_hint')" />
                            <x-common.select name="background" :label="__('body.design_background')" :value="$design->background" hide-placeholder
                                :options="collect(\App\Support\Printing\PrintDesign::BACKGROUNDS)->mapWithKeys(fn ($b) => [$b => __('body.design_background_'.$b)])->all()" />
                        </div>
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
                            <details class="rounded-xl border border-line p-3" @if (! empty($types[$type]['terms']) || ! empty($types[$type]['footer']) || ! empty($types[$type]['layout'])) open @endif>
                                <summary class="cursor-pointer text-sm font-semibold text-ink">{{ $label }}</summary>
                                <div class="mt-3 space-y-3">
                                    <x-common.select :name="'types['.$type.'][layout]'" :label="__('body.design_type_layout')" :value="$types[$type]['layout'] ?? ''" :placeholder="__('body.design_type_layout_same')"
                                        :options="collect(\App\Support\Printing\PrintDesign::LAYOUTS)->mapWithKeys(fn ($l) => [$l => __('body.design_layout_'.$l)])->all()" />
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
            <x-common.button type="submit">{{ in_array($tab, ['theme', 'layout', 'dashboard', 'menu'], true) ? __('body.theme_save_draft') : __('body.save_changes') }}</x-common.button>
        </div>
    </form>
    @endif
</x-layouts.app>
