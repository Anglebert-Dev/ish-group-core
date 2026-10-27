<x-frontend::admin.card :title="__('body.website_section_hero')">
    <x-frontend::admin.toggle :name="$n('home.hero.show')" :label="__('body.website_show_section')" :checked="(bool) $v('home.hero.show')" />
    <x-common.input :name="$n('home.hero.tagline')" :label="__('body.website_tagline')" :value="$v('home.hero.tagline')" />
    <x-common.input :name="$n('home.hero.title')" :label="__('body.website_title')" :value="$v('home.hero.title')" />
    <x-common.textarea :name="$n('home.hero.subtitle')" :label="__('body.website_subtitle')" :value="$v('home.hero.subtitle')" :rows="2" />
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input :name="$n('home.hero.primary_label')" :label="__('body.website_primary_button')" :value="$v('home.hero.primary_label')" :hint="__('body.website_primary_button_hint')" />
        <x-common.input :name="$n('home.hero.secondary_label')" :label="__('body.website_secondary_button')" :value="$v('home.hero.secondary_label')" :hint="__('body.website_secondary_button_hint')" />
    </div>
</x-frontend::admin.card>

<x-frontend::admin.card :title="__('body.website_section_features')">
    <x-frontend::admin.toggle :name="$n('home.features.show')" :label="__('body.website_show_section')" :checked="(bool) $v('home.features.show')" />
    <x-common.input :name="$n('home.features.tagline')" :label="__('body.website_tagline')" :value="$v('home.features.tagline')" />
    <x-common.input :name="$n('home.features.title')" :label="__('body.website_title')" :value="$v('home.features.title')" />
    <x-common.textarea :name="$n('home.features.description')" :label="__('body.website_description')" :value="$v('home.features.description')" :rows="2" />
    <x-frontend::admin.repeater :name="$n('home.features.items')" :rows="$v('home.features.items') ?? []" :max="12"
        :add-label="__('body.website_add_feature')"
        :fields="['title' => ['label' => __('body.website_title')], 'description' => ['label' => __('body.website_description'), 'type' => 'textarea', 'rows' => 2]]" />
</x-frontend::admin.card>

<x-frontend::admin.card :title="__('body.website_section_about')">
    <x-frontend::admin.toggle :name="$n('home.about.show')" :label="__('body.website_show_section')" :checked="(bool) $v('home.about.show')" />
    <x-common.input :name="$n('home.about.tagline')" :label="__('body.website_tagline')" :value="$v('home.about.tagline')" />
    <x-common.input :name="$n('home.about.title')" :label="__('body.website_title')" :value="$v('home.about.title')" />
    <x-common.textarea :name="$n('home.about.description')" :label="__('body.website_description')" :value="$v('home.about.description')" :rows="3" />
</x-frontend::admin.card>

<x-frontend::admin.card :title="__('body.website_section_cta')" :description="__('body.website_section_cta_hint')">
    <x-frontend::admin.toggle :name="$n('home.cta.show')" :label="__('body.website_show_section')" :checked="(bool) $v('home.cta.show')" />
    <x-common.input :name="$n('home.cta.title')" :label="__('body.website_title')" :value="$v('home.cta.title')" />
    <x-common.input :name="$n('home.cta.subtitle')" :label="__('body.website_subtitle')" :value="$v('home.cta.subtitle')" />
    <x-common.input :name="$n('home.cta.button')" :label="__('body.website_button')" :value="$v('home.cta.button')" />
</x-frontend::admin.card>

<x-frontend::admin.card :title="__('body.website_section_contact')" :description="__('body.website_section_contact_hint')">
    <x-frontend::admin.toggle :name="$n('home.contact.show')" :label="__('body.website_show_section')" :checked="(bool) $v('home.contact.show')" />
    <x-common.input :name="$n('home.contact.tagline')" :label="__('body.website_tagline')" :value="$v('home.contact.tagline')" />
    <x-common.input :name="$n('home.contact.title')" :label="__('body.website_title')" :value="$v('home.contact.title')" />
    <x-common.textarea :name="$n('home.contact.lead')" :label="__('body.website_description')" :value="$v('home.contact.lead')" :rows="2" />
</x-frontend::admin.card>
