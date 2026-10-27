<?php

namespace Modules\Frontend\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Frontend\Services\SiteService;

/**
 * Rules per editor tab ({tab} route parameter).
 */
class UpdateWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $text = ['nullable', 'string', 'max:255'];
        $long = ['nullable', 'string', 'max:5000'];
        $flag = ['nullable', 'boolean'];

        return match ($this->route('tab')) {
            'general' => [
                'slug' => [
                    'required', 'string', 'min:2', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                    Rule::notIn(SiteService::RESERVED_SLUGS),
                    Rule::unique('frontend_sites', 'slug')->ignore(
                        $this->user('staff')?->business_id,
                        'business_id',
                    ),
                ],
                'is_published' => $flag,
                'general.seo_title' => $text,
                'general.seo_description' => ['nullable', 'string', 'max:300'],
                'general.allow_registration' => $flag,
                'contact.email' => ['nullable', 'email', 'max:255'],
                'contact.phone' => ['nullable', 'string', 'max:50'],
                'contact.address' => $text,
            ],
            'home' => [
                'home.hero.show' => $flag,
                'home.hero.tagline' => $text,
                'home.hero.title' => $text,
                'home.hero.subtitle' => $long,
                'home.hero.primary_label' => ['nullable', 'string', 'max:60'],
                'home.hero.secondary_label' => ['nullable', 'string', 'max:60'],
                'home.features.show' => $flag,
                'home.features.tagline' => $text,
                'home.features.title' => $text,
                'home.features.description' => $long,
                'home.features.items' => ['nullable', 'array', 'max:12'],
                'home.features.items.*.title' => $text,
                'home.features.items.*.description' => $long,
                'home.about.show' => $flag,
                'home.about.tagline' => $text,
                'home.about.title' => $text,
                'home.about.description' => $long,
                'home.cta.show' => $flag,
                'home.cta.title' => $text,
                'home.cta.subtitle' => $text,
                'home.cta.button' => ['nullable', 'string', 'max:60'],
                'home.contact.show' => $flag,
                'home.contact.tagline' => $text,
                'home.contact.title' => $text,
                'home.contact.lead' => $long,
            ],
            'pages' => [
                'pages.about.tagline' => $text,
                'pages.about.title' => $text,
                'pages.about.body' => ['nullable', 'string', 'max:20000'],
                'pages.services.tagline' => $text,
                'pages.services.title' => $text,
                'pages.services.items' => ['nullable', 'array', 'max:30'],
                'pages.services.items.*.title' => $text,
                'pages.services.items.*.description' => $long,
                'pages.contact.tagline' => $text,
                'pages.contact.title' => $text,
                'pages.contact.lead' => $long,
                'pages.privacy.title' => $text,
                'pages.privacy.updated' => ['nullable', 'string', 'max:60'],
                'pages.privacy.sections' => ['nullable', 'array', 'max:30'],
                'pages.privacy.sections.*.title' => $text,
                'pages.privacy.sections.*.body' => $long,
                'pages.terms.title' => $text,
                'pages.terms.updated' => ['nullable', 'string', 'max:60'],
                'pages.terms.sections' => ['nullable', 'array', 'max:30'],
                'pages.terms.sections.*.title' => $text,
                'pages.terms.sections.*.body' => $long,
            ],
            'layout' => [
                'header.show_about' => $flag,
                'header.show_services' => $flag,
                'header.show_contact' => $flag,
                'header.show_account' => $flag,
                'footer.blurb' => $long,
                'footer.social.facebook' => ['nullable', 'url', 'max:255'],
                'footer.social.instagram' => ['nullable', 'url', 'max:255'],
                'footer.social.x' => ['nullable', 'url', 'max:255'],
                'footer.social.linkedin' => ['nullable', 'url', 'max:255'],
                'footer.social.whatsapp' => ['nullable', 'url', 'max:255'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge(['slug' => strtolower(trim((string) $this->input('slug')))]);
        }
    }
}
