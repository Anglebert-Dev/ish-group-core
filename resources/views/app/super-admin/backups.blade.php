<x-layouts.app :title="__('body.backups')">
    <x-superadmin.nav />

    <x-common.page-header :title="__('body.backups')" :description="__('body.backups_description')">
        <x-slot:actions>
            <form action="{{ route('superadmin.backups.store') }}" method="POST">
                @csrf
                <x-common.button type="submit" size="sm">{{ __('body.backup_now') }}</x-common.button>
            </form>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 bg-white border border-line rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                        <th class="px-5 py-3 font-semibold">{{ __('body.name') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.created') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.backup_size') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($backups as $backup)
                        <tr class="hover:bg-surface">
                            <td class="px-5 py-3.5 font-mono text-xs text-ink">{{ $backup['name'] }}</td>
                            <td class="px-5 py-3.5 text-muted whitespace-nowrap">@datetime(\Carbon\Carbon::createFromTimestamp($backup['created_at']))</td>
                            <td class="px-5 py-3.5 whitespace-nowrap">{{ \Illuminate\Support\Number::fileSize($backup['size'], 1) }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <x-common.action-button :href="route('superadmin.backups.download', $backup['name'])">{{ __('body.backup_download') }}</x-common.action-button>
                                <x-common.action-button :action="route('superadmin.backups.destroy', $backup['name'])" method="DELETE" color="danger"
                                    data-confirm data-confirm-title="{{ __('body.delete') }} {{ $backup['name'] }}?" data-confirm-variant="danger">{{ __('body.delete') }}</x-common.action-button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-muted">{{ __('body.backup_none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="space-y-4">
            <form action="{{ route('superadmin.backups.settings') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                @csrf @method('PUT')
                @if ($lastAt)
                    <p class="text-sm text-muted">{{ __('body.backup_last') }}: @datetime($lastAt)</p>
                @endif
                <input type="hidden" name="backup_daily" value="0">
                <x-common.checkbox name="backup_daily" :label="__('body.backup_daily')" :description="__('body.backup_daily_hint')" :checked="$daily" />
                <x-common.input name="backup_keep" type="number" min="0" :label="__('body.backup_keep')" :value="$keep" :hint="__('body.backup_keep_hint')" required />
                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
            </form>
            <div class="rounded-xl border border-warning/40 bg-amber-50 p-4 text-sm text-amber-900">{{ __('body.backup_offsite_hint') }}</div>
        </div>
    </div>
</x-layouts.app>
