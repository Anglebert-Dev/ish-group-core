<x-frontend::layouts.public :title="__('body.my_account')" :panel="true">
    <x-common.page-header
        :title="__('body.account_welcome', ['name' => Str::of($customer->name)->before(' ')])"
        :description="__('body.my_account_description')"
    >
        <x-slot:actions>
            <x-common.button href="{{ route('account.profile') }}" variant="secondary" size="sm">{{ __('body.profile') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white border border-line rounded-xl p-5 space-y-4">
            <h2 class="font-semibold text-ink">{{ __('body.account_details') }}</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-muted">{{ __('body.full_name') }}</dt>
                    <dd class="text-ink font-medium mt-0.5">{{ $customer->name }}</dd>
                </div>
                <div>
                    <dt class="text-muted">{{ __('body.email_address') }}</dt>
                    <dd class="text-ink font-medium mt-0.5">{{ $customer->email }}</dd>
                </div>
                <div>
                    <dt class="text-muted">{{ __('body.phone') }}</dt>
                    <dd class="text-ink font-medium mt-0.5">{{ $customer->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted">{{ __('body.member_since') }}</dt>
                    <dd class="text-ink font-medium mt-0.5">@datetime($customer->created_at)</dd>
                </div>
            </dl>
        </div>

        <div class="relative bg-white border border-line rounded-xl p-5">
            <div class="absolute left-0 top-0 bottom-0 w-1 bg-brand rounded-l-xl"></div>
            <p class="font-semibold text-ink mb-2">{{ __('body.need_help') }}</p>
            <p class="text-sm text-muted mb-4">{{ __('body.need_help_description') }}</p>
            <a href="{{ route('contact') }}" class="inline-flex min-h-[2.75rem] w-full items-center justify-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">
                {{ __('body.contact_us') }}
            </a>
        </div>
    </div>
</x-frontend::layouts.public>
