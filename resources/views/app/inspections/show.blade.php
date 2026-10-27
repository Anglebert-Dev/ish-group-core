<x-layouts.app title="Inspection Details">
    <div data-details-page>
        <x-common.details.header
            title="Inspection Details"
            subtitle="Findings from a single inspection."
            :back-href="route('inspections.index')"
            :back-label="__('body.back')"
        />

        <x-common.details.summary>
            <x-common.details.summary-item label="Module" value="Inspections" />
            <x-common.details.summary-item label="Status" value="Scaffolded" />
        </x-common.details.summary>

        <x-common.details.section title="Overview" icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" tone="ink">
            <x-common.empty-state
                title="No inspection details yet"
                description="This module is scaffolded and ready to be wired up to real data." />
        </x-common.details.section>
    </div>
</x-layouts.app>
