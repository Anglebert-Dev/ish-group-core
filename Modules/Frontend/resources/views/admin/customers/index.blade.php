<x-layouts.app :title="__('body.website_customers')">
    <x-common.page-header :title="__('body.website_customers')" :description="__('body.website_customers_description')" />

    <form method="GET" class="mb-4 max-w-sm">
        <x-common.input name="q" :value="$search" :placeholder="__('body.search')" compact />
    </form>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.full_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.email_address') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.phone') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.member_since') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('body.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">{{ $customer->name }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $customer->email }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $customer->phone ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">@datetime($customer->created_at)</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$customer->status->label()" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            @if (\App\Helpers\AuthHelper::can('update website.customer'))
                                <form action="{{ route('frontend.admin.customers.status', $customer) }}" method="POST" class="inline">
                                    @csrf @method('PUT')
                                    @if ($customer->isBanned())
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="text-success hover:opacity-80 font-medium text-sm">{{ __('body.reactivate') }}</button>
                                    @else
                                        <input type="hidden" name="status" value="banned">
                                        <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                            data-confirm
                                            data-confirm-title="{{ __('body.ban') }} {{ $customer->name }}?"
                                            data-confirm-variant="danger"
                                            data-confirm-text="{{ __('body.ban') }}"
                                        >{{ __('body.ban') }}</button>
                                    @endif
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('body.no_customers_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$customers" class="border-t border-line" />
    </div>
</x-layouts.app>
