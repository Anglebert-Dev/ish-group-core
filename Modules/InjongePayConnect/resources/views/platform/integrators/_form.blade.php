<x-common.input name="name" :label="__('body.name')" :value="old('name', $integrator?->name)" required />
<x-common.select name="profile" :label="__('injongepayconnect::connect.profile')" :options="$profiles" :value="old('profile', $integrator?->profile)" :hint="__('injongepayconnect::connect.profile_hint')" required hide-placeholder />
<x-common.input name="contact_email" type="email" :label="__('injongepayconnect::connect.contact_email')" :value="old('contact_email', $integrator?->contact_email)" />
<x-common.textarea name="notes" :label="__('injongepayconnect::connect.notes')" :value="old('notes', $integrator?->notes)" />
