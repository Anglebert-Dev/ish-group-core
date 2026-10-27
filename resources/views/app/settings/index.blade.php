@php
    $brandColor = old('brand_color', $setting->brandColor());
@endphp

<x-layouts.app :title="__('body.settings')">
    <x-common.page-header :title="__('body.settings')" :description="__('body.settings_description')" />

    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 space-y-5">
            <div>
                <h2 class="font-display font-bold text-ink">{{ __('body.branding') }}</h2>
                <p class="text-sm text-muted mt-1">{{ __('body.company_name_hint') }}</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <x-common.input
                    name="name"
                    :label="__('body.company_name')"
                    :value="old('name', $setting->name)"
                    required
                />

                <x-common.input
                    type="email"
                    name="email"
                    :label="__('body.business_email')"
                    :value="old('email', $setting->email)"
                    :hint="__('body.business_email_hint')"
                />

                <div class="flex min-w-0 flex-col gap-2 w-full">
                    <label for="brand_color" class="text-sm font-semibold text-ink">
                        {{ __('body.brand_color') }}
                        <span class="text-danger">*</span>
                    </label>
                    <div class="flex items-center gap-3">
                        <input
                            type="color"
                            id="brand_color_picker"
                            value="{{ strtolower($brandColor) }}"
                            oninput="document.getElementById('brand_color').value = this.value.toUpperCase()"
                            class="h-12 w-16 shrink-0 rounded-xl border border-line bg-white p-1 cursor-pointer shadow-sm hover:shadow-md transition-all duration-200"
                        >
                        <input
                            type="text"
                            name="brand_color"
                            id="brand_color"
                            value="{{ $brandColor }}"
                            required
                            maxlength="7"
                            oninput="if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('brand_color_picker').value = this.value"
                            class="flex-1 h-12 rounded-xl border px-4 text-sm bg-white shadow-sm hover:shadow-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand uppercase @error('brand_color') border-danger @else border-line @enderror"
                        >
                    </div>
                    @error('brand_color')
                        <p class="text-xs text-danger" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 space-y-5">
            <div>
                <h2 class="font-display font-bold text-ink">{{ __('body.assets') }}</h2>
                <p class="text-sm text-muted mt-1">{{ __('body.web_logo_hint') }}</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <x-common.file-input
                    name="logo"
                    :label="__('body.web_logo')"
                    accept="image/*"
                    :max-size-mb="4"
                    :existing-url="$setting->logoUrl()"
                />

                <x-common.file-input
                    name="favicon"
                    :label="__('body.favicon')"
                    accept="image/*"
                    :max-size-mb="1"
                    :existing-url="$setting->faviconUrl()"
                />
            </div>
        </div>

        <div class="flex [&>*]:w-full sm:[&>*]:w-auto sm:justify-end">
            <x-common.button type="submit">{{ __('body.save_changes') }}</x-common.button>
        </div>
    </form>
</x-layouts.app>
