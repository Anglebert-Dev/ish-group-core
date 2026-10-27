{{-- Public contact form (posts to the current site). --}}
<form action="{{ route('contact.send') }}" method="POST" class="relative overflow-hidden rounded-3xl border border-line bg-white p-6 sm:p-8 shadow-sm shadow-slate-200/60 space-y-5">
    @csrf
    <div class="absolute inset-x-0 top-0 h-1 bg-brand"></div>
    <div>
        <h3 class="font-display font-bold text-xl text-ink tracking-tight">{{ __('body.contact_form_title') }}</h3>
        <p class="text-sm text-muted mt-1.5 leading-relaxed">{{ __('body.contact_form_subtitle') }}</p>
    </div>

    {{-- Honeypot: hidden from people, filled by bots. --}}
    <div class="hidden" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-common.input name="name" :label="__('body.full_name')" :value="old('name', auth('customer')->user()?->name)" required autocomplete="name" />
        <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email', auth('customer')->user()?->email)" required autocomplete="email" />
        <x-common.input name="phone" :label="__('body.phone')" :value="old('phone')" autocomplete="tel" />
        <x-common.input name="subject" :label="__('body.contact_subject')" :value="old('subject')" />
    </div>
    <x-common.textarea name="message" :label="__('body.message')" :value="old('message')" :rows="5" required />

    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] px-6 py-3 transition-colors w-full sm:w-auto">
            {{ __('body.send_message') }}
        </button>
        <p class="text-xs text-muted">{{ __('body.contact_form_note') }}</p>
    </div>
</form>
