<x-layouts.public title="Search Vehicles">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10">
        <x-common.page-header title="Available Vehicles" description="Showing vehicles that match your search dates." />
        <p class="text-sm text-muted -mt-4 mb-2">This step will later be wired to live availability — for now it reuses the fleet listing below.</p>
    </div>

    @php($noTopPad = true)
    @include('public.vehicles._grid')
</x-layouts.public>
