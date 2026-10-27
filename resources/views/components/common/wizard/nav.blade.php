@props([
    'showBack' => true,
    'showNext' => true,
    'showSubmit' => false,
    'cancelHref' => null,
    'submitLabel' => null,
    'nextLabel' => null,
    'backLabel' => null,
])

<div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3 pt-3 border-t border-line" data-wizard-nav>
    @if ($cancelHref)
        <div class="flex order-first [&>*]:w-full sm:[&>*]:w-auto">
            <x-common.button
                :href="$cancelHref"
                :variant="$showBack ? 'ghost' : 'secondary'"
                :size="$showBack ? 'sm' : 'md'"
                class="w-full sm:w-auto"
            >{{ __('body.cancel') }}</x-common.button>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:ml-auto items-stretch sm:items-center gap-2 sm:gap-3 [&>*]:w-full sm:[&>*]:w-auto">
        @if ($showBack)
            <x-common.button type="button" variant="secondary" class="w-full sm:w-auto" data-wizard-back>
                {{ $backLabel ?? __('body.back') }}
            </x-common.button>
        @endif

        @if ($showNext)
            <x-common.button type="button" class="w-full sm:w-auto" data-wizard-next>
                {{ $nextLabel ?? __('body.next') }}
            </x-common.button>
        @endif

        @if ($showSubmit)
            <x-common.button type="submit" class="w-full sm:w-auto">
                {{ $submitLabel ?? __('body.save') }}
            </x-common.button>
        @endif
    </div>
</div>
