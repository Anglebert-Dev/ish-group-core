    <div>
        <div class="px-5 py-4 border-b border-line">
            <p class="font-semibold text-ink">{{ __('distribution::distribution.tokens') }}</p>
            <p class="text-sm text-muted">{{ __('distribution::distribution.tokens_hint') }}</p>
        </div>

        @if (session('link_key'))
            <div class="m-5 rounded-xl border border-warning/30 bg-warning/10 px-5 py-4">
                <p class="text-sm text-ink">{{ __('distribution::distribution.link_key_once') }}</p>
                <p class="mt-2 font-mono text-xs break-all text-ink select-all bg-card border border-warning/30 rounded-lg p-3">{{ session('link_key') }}</p>
            </div>
        @endif

        @unless ($site->canReceiveLinkKey())
            <p class="p-5 border-b border-line text-sm text-muted">{{ __('distribution::distribution.link_key_too_early') }}</p>
        @else
        <form method="POST" action="{{ route('superadmin.distribution.sites.link-key', $site) }}" class="p-5 border-b border-line space-y-3">
            @csrf
            <p class="text-sm text-muted">{{ __('distribution::distribution.link_key_hint') }}</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[240px]">
                    <x-common.input name="email" type="email" :label="__('distribution::distribution.email_label')" :value="$site->contact_email" :hint="__('distribution::distribution.link_key_email_hint')" />
                </div>
                <x-common.button type="submit" size="sm" variant="secondary">{{ __('distribution::distribution.link_key') }}</x-common.button>
            </div>
            <input type="hidden" name="revoke_others" value="0">
            <x-common.checkbox name="revoke_others" value="1" :checked="true" inline :label="__('distribution::distribution.link_key_revoke_others')" />
        </form>
        @endunless
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-line">
                    @forelse ($site->tokens as $token)
                        <tr>
                            <td class="px-5 py-3.5"><span class="font-mono">ish_…{{ $token->last4 }}</span> <span class="text-xs text-muted">({{ $token->build_id ? __('distribution::distribution.token_from_zip') : __('distribution::distribution.token_from_link') }})</span></td>
                            <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ __('distribution::distribution.issued') }} {{ $token->created_at?->format('d M Y') }}</td>
                            <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ __('distribution::distribution.last_used') }}: {{ $token->last_used_at?->diffForHumans() ?? __('distribution::distribution.never') }}</td>
                            <td class="px-5 py-3.5 text-right">
                                @if ($token->isRevoked())
                                    <x-common.badge color="muted">{{ __('distribution::distribution.revoked') }}</x-common.badge>
                                @else
                                    <x-common.action-button :action="route('superadmin.distribution.sites.tokens.revoke', [$site, $token])" color="danger"
                                        data-confirm data-confirm-title="{{ __('distribution::distribution.revoke_confirm') }}" data-confirm-variant="danger">
                                        {{ __('distribution::distribution.revoke') }}
                                    </x-common.action-button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-muted">{{ __('distribution::distribution.no_tokens') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
