<x-frontend::admin.card :title="__('body.website_header')" :description="__('body.website_header_hint')">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <x-frontend::admin.toggle :name="$n('header.show_about')" :label="__('body.about_us')" :checked="(bool) $v('header.show_about')" />
        <x-frontend::admin.toggle :name="$n('header.show_services')" :label="__('body.services')" :checked="(bool) $v('header.show_services')" />
        <x-frontend::admin.toggle :name="$n('header.show_contact')" :label="__('body.contact')" :checked="(bool) $v('header.show_contact')" />
        <x-frontend::admin.toggle :name="$n('header.show_account')" :label="__('body.website_show_account')" :description="__('body.website_show_account_hint')" :checked="(bool) $v('header.show_account')" />
    </div>
</x-frontend::admin.card>

<x-frontend::admin.card :title="__('body.website_footer')">
    <x-common.textarea :name="$n('footer.blurb')" :label="__('body.website_footer_blurb')" :value="$v('footer.blurb')" :rows="2" />
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        @foreach (['facebook', 'instagram', 'x', 'linkedin', 'whatsapp'] as $network)
            <x-common.input type="url" :name="$n('footer.social.'.$network)" :label="__('body.social_'.$network)" :value="$v('footer.social.'.$network)" placeholder="https://" />
        @endforeach
    </div>
    <p class="text-xs text-muted">{{ __('body.website_footer_contact_hint') }}</p>
</x-frontend::admin.card>
