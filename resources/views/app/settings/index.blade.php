@php
    $brandColor = old('brand_color', $setting->brandColor());
    $secondaryColor = old('secondary_color', $brand->secondaryColor);
@endphp

<x-layouts.app :title="__('body.branding')">
    <x-common.page-header :title="__('body.branding')" :description="__('body.branding_description')" />

    <nav class="flex flex-wrap gap-1.5 mb-6">
        @foreach ($tabs as $item)
            <a href="{{ route('settings.index', ['tab' => $item]) }}"
               class="inline-flex items-center rounded-full px-3.5 py-1.5 text-sm font-medium border transition-colors {{ $tab === $item ? 'bg-brand text-white border-brand' : 'bg-white text-body border-line hover:border-brand hover:text-brand' }}">
                {{ __('body.brand_tab_'.$item) }}
            </a>
        @endforeach
    </nav>

    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="section" value="{{ $tab }}">

        @if ($tab === 'details')
            <div class="bg-white border border-line rounded-xl p-4 sm:p-6 space-y-5">
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
            <div class="bg-white border border-line rounded-xl p-4 sm:p-6 space-y-5">
                <p class="text-sm text-muted">{{ __('body.brand_look_hint') }}</p>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    @foreach ([['brand_color', $brandColor, __('body.brand_color'), null, true], ['secondary_color', $secondaryColor, __('body.brand_secondary_color'), __('body.brand_secondary_color_hint'), false]] as [$field, $value, $label, $hint, $required])
                        <div class="flex min-w-0 flex-col gap-2 w-full">
                            <label for="{{ $field }}" class="text-sm font-semibold text-ink">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
                            <div class="flex items-center gap-3">
                                <input type="color" value="{{ strtolower($value) }}" aria-label="{{ $label }}"
                                       oninput="document.getElementById('{{ $field }}').value = this.value.toUpperCase()"
                                       class="h-12 w-16 shrink-0 rounded-xl border border-line bg-white p-1 cursor-pointer shadow-sm">
                                <input type="text" name="{{ $field }}" id="{{ $field }}" value="{{ $value }}" maxlength="7" @required($required)
                                       class="flex-1 h-12 rounded-xl border px-4 text-sm bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand uppercase @error($field) border-danger @else border-line @enderror">
                            </div>
                            @if ($hint)<p class="text-xs text-muted">{{ $hint }}</p>@endif
                            @error($field)<p class="text-xs text-danger" role="alert">{{ $message }}</p>@enderror
                        </div>
                    @endforeach

                    <x-common.select name="font" :label="__('body.brand_font')" :value="old('font', $brand->font)" :options="$fonts" hide-placeholder :hint="__('body.brand_font_hint')" />
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

        @if ($tab === 'stamp')
            <div class="bg-white border border-line rounded-xl p-4 sm:p-6 space-y-5">
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
            <div class="bg-white border border-line rounded-xl p-4 sm:p-6 space-y-5">
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

        <div class="flex [&>*]:w-full sm:[&>*]:w-auto sm:justify-end">
            <x-common.button type="submit">{{ __('body.save_changes') }}</x-common.button>
        </div>
    </form>
</x-layouts.app>
