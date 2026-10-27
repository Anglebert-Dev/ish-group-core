<x-frontend::admin.card :title="__('body.website_publishing')" :description="__('body.website_publishing_hint')">
    <x-frontend::admin.toggle name="is_published" :label="__('body.website_publish')" :checked="(bool) old('is_published', $site->is_published)" />
    <x-common.input name="slug" :label="__('body.website_address')" :value="old('slug', $site->slug)" :hint="url('/s').'/…'" required />
</x-frontend::admin.card>

<x-frontend::admin.card :title="__('body.website_seo')" :description="__('body.website_seo_hint')">
    <x-common.input :name="$n('general.seo_title')" :label="__('body.website_seo_title')" :value="$v('general.seo_title')" />
    <x-common.textarea :name="$n('general.seo_description')" :label="__('body.website_seo_description')" :value="$v('general.seo_description')" :rows="2" />
</x-frontend::admin.card>

<x-frontend::admin.card :title="__('body.website_customer_accounts')" :description="__('body.website_customer_accounts_hint')">
    <x-frontend::admin.toggle :name="$n('general.allow_login')" :label="__('body.website_allow_login')"
        :description="__('body.website_allow_login_hint')" :checked="(bool) $v('general.allow_login')" />
    <x-frontend::admin.toggle :name="$n('general.allow_registration')" :label="__('body.website_allow_registration')"
        :description="__('body.website_allow_registration_hint')" :checked="(bool) $v('general.allow_registration')" />
</x-frontend::admin.card>

<x-frontend::admin.card :title="__('body.website_contact_details')" :description="__('body.website_contact_details_hint')">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input type="email" :name="$n('contact.email')" :label="__('body.email_address')" :value="$v('contact.email')" />
        <x-common.input :name="$n('contact.phone')" :label="__('body.phone')" :value="$v('contact.phone')" />
    </div>
    <x-common.input :name="$n('contact.address')" :label="__('body.address')" :value="$v('contact.address')" />
</x-frontend::admin.card>
