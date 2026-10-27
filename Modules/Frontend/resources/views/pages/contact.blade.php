<x-frontend::layouts.public :title="__('body.contact')">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-brand-dark text-sm font-semibold tracking-wide mb-3">{{ __('body.contact_tagline') }}</p>
        <h1 class="font-display font-bold text-3xl text-ink mb-8">{{ __('body.contact_title', ['company' => $company]) }}</h1>

        <form class="grid grid-cols-1 sm:grid-cols-2 gap-5 max-w-2xl">
            <div>
                <label class="block text-xs font-semibold text-muted mb-1.5">{{ __('body.full_name') }}</label>
                <input type="text" class="w-full h-11 rounded-lg border border-line px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
            </div>
            <div>
                <label class="block text-xs font-semibold text-muted mb-1.5">{{ __('body.email_address') }}</label>
                <input type="email" class="w-full h-11 rounded-lg border border-line px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-muted mb-1.5">{{ __('body.message') }}</label>
                <textarea rows="5" class="w-full rounded-lg border border-line px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand"></textarea>
            </div>
            <div>
                <x-common.button type="submit">{{ __('body.send_message') }}</x-common.button>
            </div>
        </form>
    </div>
</x-frontend::layouts.public>
