<x-layouts.app :title="$message->name()">
    <x-common.page-header :title="$message->subject ?: __('body.contact_message_from', ['name' => $message->name])" :description="$message->email">
        <x-slot:actions>
            <x-common.button href="{{ route('frontend.admin.messages.index') }}" variant="secondary" size="sm">← {{ __('body.website_messages') }}</x-common.button>
            <x-common.button href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.($message->subject ?: __('body.contact_your_message'))) }}" size="sm">{{ __('body.contact_reply') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 bg-white border border-line rounded-xl p-5 sm:p-6 text-body leading-relaxed">{!! nl2br(e($message->message)) !!}</div>
        <div class="space-y-4">
            <x-common.info-card :rows="[
                [__('body.full_name'), $message->name],
                [__('body.email_address'), $message->email],
                [__('body.phone'), $message->phone],
                [__('body.when'), \App\Libraries\GeneralLibrary::formatDateTime($message->created_at)],
            ]" />
            <form action="{{ route('frontend.admin.messages.destroy', $message) }}" method="POST">
                @csrf @method('DELETE')
                <x-common.button type="submit" variant="secondary" size="sm" data-confirm data-confirm-title="{{ __('body.delete') }}?" data-confirm-variant="danger">{{ __('body.delete') }}</x-common.button>
            </form>
        </div>
    </div>
</x-layouts.app>
