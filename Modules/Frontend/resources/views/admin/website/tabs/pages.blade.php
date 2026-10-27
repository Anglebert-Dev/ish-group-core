@php
    $sectionFields = ['title' => ['label' => __('body.website_heading')], 'body' => ['label' => __('body.website_text'), 'type' => 'textarea', 'rows' => 4]];
@endphp

<x-frontend::admin.card :title="__('body.about_us')">
    <x-common.input :name="$n('pages.about.tagline')" :label="__('body.website_tagline')" :value="$v('pages.about.tagline')" />
    <x-common.input :name="$n('pages.about.title')" :label="__('body.website_title')" :value="$v('pages.about.title')" />
    <x-common.textarea :name="$n('pages.about.body')" :label="__('body.website_text')" :value="$v('pages.about.body')" :rows="8" :hint="__('body.website_paragraphs_hint')" />
</x-frontend::admin.card>

<x-frontend::admin.card :title="__('body.services')">
    <x-common.input :name="$n('pages.services.tagline')" :label="__('body.website_tagline')" :value="$v('pages.services.tagline')" />
    <x-common.input :name="$n('pages.services.title')" :label="__('body.website_title')" :value="$v('pages.services.title')" />
    <x-frontend::admin.repeater :name="$n('pages.services.items')" :rows="$v('pages.services.items') ?? []"
        :add-label="__('body.website_add_service')"
        :fields="['title' => ['label' => __('body.website_title')], 'description' => ['label' => __('body.website_description'), 'type' => 'textarea', 'rows' => 2]]" />
</x-frontend::admin.card>

<x-frontend::admin.card :title="__('body.contact')" :description="__('body.website_contact_page_hint')">
    <x-common.input :name="$n('pages.contact.tagline')" :label="__('body.website_tagline')" :value="$v('pages.contact.tagline')" />
    <x-common.input :name="$n('pages.contact.title')" :label="__('body.website_title')" :value="$v('pages.contact.title')" />
    <x-common.textarea :name="$n('pages.contact.lead')" :label="__('body.website_description')" :value="$v('pages.contact.lead')" :rows="2" />
</x-frontend::admin.card>

@foreach (['privacy' => __('body.privacy_policy'), 'terms' => __('body.terms_and_conditions')] as $doc => $docLabel)
    <x-frontend::admin.card :title="$docLabel">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <x-common.input :name="$n('pages.'.$doc.'.title')" :label="__('body.website_title')" :value="$v('pages.'.$doc.'.title')" />
            <x-common.input :name="$n('pages.'.$doc.'.updated')" :label="__('body.website_last_updated')" :value="$v('pages.'.$doc.'.updated')" :hint="__('body.website_last_updated_hint')" />
        </div>
        <x-frontend::admin.repeater :name="$n('pages.'.$doc.'.sections')" :rows="$v('pages.'.$doc.'.sections') ?? []"
            :add-label="__('body.website_add_section')" :fields="$sectionFields" />
    </x-frontend::admin.card>
@endforeach
