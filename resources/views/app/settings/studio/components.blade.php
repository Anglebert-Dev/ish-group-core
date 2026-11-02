{{-- Branding studio: one of everything, to check colours, shapes and type at a glance. --}}
<x-layouts.app :title="__('body.studio_components')">
    <x-common.page-header :title="__('body.studio_components')" :description="__('body.studio_components_hint')">
        <x-slot:actions>
            <x-common.button variant="secondary">{{ __('body.cancel') }}</x-common.button>
            <x-common.button>{{ __('body.save') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="bg-card border border-line rounded-xl p-5 space-y-4">
            <p class="font-display font-bold text-ink text-lg">{{ __('body.studio_buttons') }}</p>
            <div class="flex flex-wrap gap-2">
                <x-common.button>{{ __('body.save') }}</x-common.button>
                <x-common.button variant="secondary">{{ __('body.cancel') }}</x-common.button>
                <x-common.button variant="ghost">{{ __('body.view') }}</x-common.button>
                <x-common.button variant="danger">{{ __('body.delete') }}</x-common.button>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-common.badge>{{ __('body.studio_badge_brand') }}</x-common.badge>
                <x-common.badge color="success">{{ __('body.active') }}</x-common.badge>
                <x-common.badge color="warning">{{ __('body.disabled') }}</x-common.badge>
                <x-common.badge color="danger">{{ __('body.studio_badge_overdue') }}</x-common.badge>
                <x-common.badge color="muted">{{ __('body.studio_badge_draft') }}</x-common.badge>
            </div>
            <p class="text-sm text-body">{{ __('body.studio_text_sample') }} <a href="#" class="text-link font-semibold hover:underline">{{ __('body.studio_link') }}</a></p>
            <div class="flex gap-2">
                @foreach (range(1, 6) as $i)
                    <span class="h-6 flex-1 rounded-md bg-chart-{{ $i }}" title="chart-{{ $i }}"></span>
                @endforeach
            </div>
        </div>

        <div class="bg-card border border-line rounded-xl p-5 space-y-4">
            <p class="font-display font-bold text-ink text-lg">{{ __('body.studio_fields') }}</p>
            <x-common.input name="studio_name" :label="__('body.name')" placeholder="Kigali Sun" />
            <x-common.select name="studio_select" :label="__('body.status')" :options="['a' => __('body.active'), 'b' => __('body.disabled')]" />
            <x-common.checkbox name="studio_check" :label="__('body.studio_checkbox')" checked />
        </div>

        <div class="space-y-3">
            <div class="rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-ink">{{ __('body.studio_alert_success') }}</div>
            <div class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-ink">{{ __('body.studio_alert_warning') }}</div>
            <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-ink">{{ __('body.studio_alert_danger') }}</div>
            <div class="rounded-xl border border-info/30 bg-info/10 px-4 py-3 text-sm text-ink">{{ __('body.studio_alert_info') }}</div>
        </div>

        <div class="bg-card border border-line rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                        <th class="px-5 py-3 font-semibold">{{ __('body.name') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.studio_amount') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ([['Lake Kivu Serena', '450,000', 'success'], ['Green Hills Academy', '1,295,000', 'warning'], ['Grace Ingabire', '27,000', 'danger']] as [$name, $amount, $color])
                        <tr class="hover:bg-surface">
                            <td class="px-5 py-3 text-ink font-medium">{{ $name }}</td>
                            <td class="px-5 py-3 text-right text-body">{{ $amount }}</td>
                            <td class="px-5 py-3"><x-common.badge :color="$color">{{ __('body.studio_status_'.$color) }}</x-common.badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
