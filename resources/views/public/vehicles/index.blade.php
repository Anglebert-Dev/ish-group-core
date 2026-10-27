<x-layouts.public :title="__('body.our_fleet')">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10">
        <x-common.page-header :title="__('body.our_fleet')" :description="__('body.vehicles_available_description', ['count' => $vehicles->total()])">
            <x-slot:actions>
                <x-common.button href="{{ route('booking.search') }}" variant="secondary" size="sm">{{ __('body.search_availability') }}</x-common.button>
            </x-slot:actions>
        </x-common.page-header>
    </div>

    @php($noTopPad = true)
    @include('public.vehicles._grid')

</x-layouts.public>
