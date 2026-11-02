<?php

namespace App\Services\Branding;

use App\Helpers\AuthHelper;
use App\Libraries\UploadLibrary;
use App\Models\Business\Business;
use App\Services\BaseService;
use App\Support\Branding\Brand;
use App\Support\Branding\Fonts;
use App\Support\Branding\LoginBranding;
use App\Support\Branding\ThemeLayout;
use App\Support\Branding\ThemeTokens;
use App\Support\Menu\AdminMenu;
use App\Support\Printing\PrintDesign;
use App\Support\Settings\BusinessSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * A business's branding (docs/branding-print-build-guide.md §3). Colour, logo
 * and favicon stay on the business; the rest lives in BusinessSettings under
 * "branding.*" (each change audited there). Stamp and signature are private
 * files (local disk), never public URLs.
 */
class BrandingService extends BaseService
{
    public const PAYMENT_TYPES = ['bank', 'mobile'];

    public const MAX_PAYMENT_METHODS = 6;

    /** Text fields of the Business details tab. */
    public const DETAILS = ['legal_name', 'short_name', 'slogan', 'description', 'locale', 'tax_id', 'registration_no', 'address', 'phone', 'website'];

    /** Optional logos, each falling back to the main logo (setting key => upload field). */
    public const EXTRA_LOGOS = ['logo_login_path' => 'logo_login', 'logo_mobile_path' => 'logo_mobile'];

    public function __construct(protected BusinessSettings $settings) {}

    public function brand(Business $business): Brand
    {
        $get = fn (string $key, mixed $default = null) => $this->settings->get('branding.'.$key, $default, $business);

        // Before branding existed, Invoicing kept its own issuer details: still honoured.
        $legacy = fn (string $key) => $this->settings->get('invoicing.'.$key, null, $business);

        return new Brand(
            name: $business->companyName(),
            legalName: $get('legal_name'),
            taxId: $get('tax_id') ?? $legacy('tax_id'),
            registrationNo: $get('registration_no'),
            address: $get('address') ?? $legacy('address'),
            phone: $get('phone') ?? $legacy('phone'),
            email: $business->contactEmail(),
            website: $get('website'),
            primaryColor: $business->brandColor(),
            secondaryColor: $this->color($get('secondary_color')) ?? '#111827',
            font: Fonts::normalise($get('font')),
            logoPath: $business->logo_path,
            logoDarkPath: $get('logo_dark_path'),
            stampPath: $get('stamp_path'),
            signaturePath: $get('signature_path'),
            signatoryName: $get('signatory_name'),
            signatoryTitle: $get('signatory_title'),
            paymentMethods: array_values(array_filter((array) $get('payment_methods', []), 'is_array')),
        );
    }

    /**
     * The design tokens for every page (docs/branding.md §2.3).
     *
     * @return array<string, string>
     */
    public function tokens(Business $business): array
    {
        return app(ThemeService::class)->tokens($business, $this->previewing($business));
    }

    /** Email fields (Branding → Emails), saved as branding.email_*. */
    public const EMAIL_FIELDS = ['email_header_color', 'email_sender_name', 'email_reply_to', 'email_footer', 'email_signature', 'email_show_contact'];

    /** @return array<string, mixed> */
    public function emailSettings(Business $business): array
    {
        return collect(self::EMAIL_FIELDS)->mapWithKeys(fn ($key) => [$key => $this->settings->get('branding.'.$key, null, $business)])->filter(fn ($value) => $value !== null)->all();
    }

    /** @param  array<string, mixed>  $data */
    public function saveEmails(Business $business, array $data): array
    {
        foreach (self::EMAIL_FIELDS as $key) {
            $value = $data[$key] ?? null;
            $this->put($business, $key, $key === 'email_show_contact' ? ($value ? true : null) : $value);
        }

        return $this->success($business, __('body.branding_saved'));
    }

    /** Login page fields (Branding → Login page): text, saved as branding.login_*. */
    public const LOGIN_TEXT = ['login_heading', 'login_welcome', 'login_panel_text', 'login_footer'];

    /**
     * What the sign-in pages show for this business (docs/branding.md §3.6).
     *
     * @return array{handle: ?string, heading: ?string, welcome: ?string, panel_text: ?string, panel_note: ?string, image: ?string, footer: ?string, show_contact: bool, contact: list<string>}
     */
    public function loginPage(Business $business): array
    {
        $get = fn (string $key) => $business->exists ? $this->settings->get('branding.'.$key, null, $business) : null;
        $brand = $business->exists ? $this->brand($business) : null;

        return [
            'handle' => $get('login_handle'),
            'heading' => $get('login_heading'),
            'welcome' => $get('login_welcome'),
            // The slogan and description fill the picture panel unless it has its own text.
            'panel_text' => $get('login_panel_text') ?? $get('slogan'),
            'panel_note' => $get('description'),
            'image' => $this->publicImage($business, 'login_image_path'),
            'footer' => $get('login_footer'),
            'show_contact' => (bool) $get('login_show_contact'),
            'contact' => $brand && $get('login_show_contact') ? array_values(array_filter([$brand->phone, $brand->email, $brand->address ? str_replace("\n", ', ', $brand->address) : null])) : [],
        ];
    }

    /**
     * @param  array{login_handle: string, login_heading?: ?string, login_welcome?: ?string, login_panel_text?: ?string, login_footer?: ?string, login_show_contact?: bool}  $data
     */
    public function saveLoginPage(Business $business, array $data, ?UploadedFile $image = null, bool $removeImage = false): array
    {
        $this->put($business, 'login_handle', strtolower($data['login_handle']));
        foreach (self::LOGIN_TEXT as $key) {
            $this->put($business, $key, $data[$key] ?? null);
        }
        $this->put($business, 'login_show_contact', ! empty($data['login_show_contact']) ? true : null);
        $this->replaceImage($business, 'login_image_path', $image, $removeImage, 'public');

        return $this->success($business, __('body.branding_saved'));
    }

    /** A free login link name from the business name ("kigali-sun-garments", "kigali-sun-garments-2"…). */
    public function suggestHandle(Business $business): string
    {
        $base = substr(trim(Str::slug($business->companyName()), '-'), 0, 34) ?: 'business';
        $base = strlen($base) < 3 ? $base.'-'.$business->id : $base;
        $handle = $base;
        for ($i = 2; app(LoginBranding::class)->handleTaken($handle, $business); $i++) {
            $handle = $base.'-'.$i;
        }

        return $handle;
    }

    /** Public URL of the logo for dark backgrounds, if uploaded. */
    public function darkLogoUrl(Business $business): ?string
    {
        return $this->publicImage($business, 'logo_dark_path');
    }

    /**
     * A logo for one place ("login", "mobile"), if uploaded; null = use the main logo.
     */
    public function logoFor(Business $business, string $place): ?string
    {
        return $this->publicImage($business, 'logo_'.$place.'_path');
    }

    /** A short text value from Business details (short name, slogan, description). */
    public function detail(Business $business, string $key): ?string
    {
        return $business->exists ? $this->settings->get('branding.'.$key, null, $business) : null;
    }

    /**
     * @param  array<string, ?UploadedFile>  $files  upload field => file
     * @param  array<string, bool>  $remove  upload field => remove
     */
    public function saveExtraLogos(Business $business, array $files, array $remove): void
    {
        foreach (self::EXTRA_LOGOS as $key => $field) {
            $this->replaceImage($business, $key, $files[$field] ?? null, (bool) ($remove[$field] ?? false), 'public');
        }
    }

    private function publicImage(Business $business, string $key): ?string
    {
        $path = $business->exists ? $this->settings->get('branding.'.$key, null, $business) : null;

        return $path ? '/storage/'.$path : null;
    }

    /**
     * The :root colour block for this business's pages, in the mode this
     * user sees (their own choice when allowed, else the business's).
     */
    public function themeCss(Business $business): string
    {
        $user = auth('staff')->user();

        return app(ThemeService::class)->css($business, $user?->business_id === $business->id ? $user : null, $this->previewing($business));
    }

    /** The theme document this person sees now (the draft while previewing). */
    public function theme(Business $business): array
    {
        return app(ThemeService::class)->effective($business, $this->previewing($business));
    }

    /**
     * Where staff land after signing in (Branding → Dashboard): the chosen
     * page if this person may open it, else the dashboard.
     */
    public function landingRoute(Business $business): string
    {
        $route = $this->theme($business)['landing'] ?? null;

        return $route && app(AdminMenu::class)->allowedItems()->contains('route', $route) ? $route : 'dashboard';
    }

    /** Layout & typography for this business's pages (draft while previewing). */
    public function layout(Business $business): ThemeLayout
    {
        return app(ThemeService::class)->layout($business, $this->previewing($business));
    }

    /**
     * Light or dark for this user right now; "system" when the device decides.
     */
    public function effectiveMode(Business $business): string
    {
        $themes = app(ThemeService::class);
        $preview = $this->previewing($business);
        $user = auth('staff')->user();
        $own = $user?->business_id === $business->id && $themes->allowsUserMode($business, $preview) ? $user->appearance : null;

        return in_array($own, ThemeTokens::MODES, true) ? $own : $themes->mode($business, $preview);
    }

    /**
     * Is the signed-in user looking at their business's unpublished draft?
     */
    public function previewing(Business $business): bool
    {
        $user = auth('staff')->user();

        return $business->exists && $user && $user->business_id === $business->id
            && (session(ThemeService::PREVIEW) === $business->id || app(ThemeService::class)->inStudio($business))
            && AuthHelper::can('update settings.setting');
    }

    /**
     * The system font (docs/branding.md §3.5), for every page's <head>.
     */
    public function font(Business $business): string
    {
        // Before the first business exists, pages show an unsaved stand-in.
        return $business->exists ? Fonts::normalise($this->settings->get('branding.font', null, $business)) : Fonts::DEFAULT;
    }

    /**
     * @param  array{name: string, email?: ?string, legal_name?: ?string, tax_id?: ?string, registration_no?: ?string, address?: ?string, phone?: ?string, website?: ?string}  $data
     */
    public function saveDetails(Business $business, array $data): array
    {
        $business->fill(['name' => $data['name'], 'email' => $data['email'] ?? null])->save();

        foreach (self::DETAILS as $key) {
            $this->put($business, $key, $data[$key] ?? null);
        }

        return $this->success($business, __('body.branding_saved'));
    }

    /**
     * @param  array{brand_color: string, secondary_color?: ?string, font?: ?string}  $data
     */
    public function saveLook(Business $business, array $data, ?UploadedFile $logo = null, ?UploadedFile $logoDark = null, ?UploadedFile $favicon = null, bool $removeLogoDark = false): array
    {
        $business->brand_color = $data['brand_color'];

        if ($logo) {
            $business->logo_path && UploadLibrary::delete($business->logo_path, 'public');
            $business->logo_path = UploadLibrary::store($logo, 'branding', 'public');
        }

        if ($favicon) {
            $business->favicon_path && UploadLibrary::delete($business->favicon_path, 'public');
            $business->favicon_path = UploadLibrary::store($favicon, 'branding', 'public');
        }

        $business->save();

        $this->put($business, 'secondary_color', $this->color($data['secondary_color'] ?? null));
        $this->put($business, 'font', $data['font'] ?? null);
        $this->replaceImage($business, 'logo_dark_path', $logoDark, $removeLogoDark, 'public');

        return $this->success($business, __('body.branding_saved'));
    }

    public function saveStampAndSignature(Business $business, array $data, ?UploadedFile $stamp = null, ?UploadedFile $signature = null, bool $removeStamp = false, bool $removeSignature = false): array
    {
        $this->replaceImage($business, 'stamp_path', $stamp, $removeStamp, 'local');
        $this->replaceImage($business, 'signature_path', $signature, $removeSignature, 'local');
        $this->put($business, 'signatory_name', $data['signatory_name'] ?? null);
        $this->put($business, 'signatory_title', $data['signatory_title'] ?? null);

        return $this->success($business, __('body.branding_saved'));
    }

    /**
     * @param  list<array<string, mixed>>  $methods
     */
    public function savePaymentMethods(Business $business, array $methods): array
    {
        $clean = collect($methods)
            ->filter(fn ($method) => is_array($method) && filled($method['number'] ?? null))
            ->map(fn (array $method) => [
                'type' => in_array($method['type'] ?? null, self::PAYMENT_TYPES, true) ? $method['type'] : 'bank',
                'label' => $this->text($method['label'] ?? null),
                'account_name' => $this->text($method['account_name'] ?? null),
                'number' => (string) $this->text($method['number']),
                'provider' => $this->text($method['provider'] ?? null),
                'branch' => $this->text($method['branch'] ?? null),
                'swift' => $this->text($method['swift'] ?? null),
            ])
            ->take(self::MAX_PAYMENT_METHODS)
            ->values()
            ->all();

        $clean === [] ? $this->settings->forget('branding.payment_methods', $business) : $this->settings->set('branding.payment_methods', $clean, $business);

        return $this->success($business, __('body.branding_saved'));
    }

    /**
     * The print design (docs/branding-print-build-guide.md §4): "print.*".
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDesign(Business $business, array $data): array
    {
        foreach (['layout', 'paper', 'accent', 'font', 'logo_position', 'logo_size', 'header_note', 'footer_text'] as $key) {
            $value = is_string($data[$key] ?? null) ? trim($data[$key]) : null;
            $value === null || $value === ''
                ? $this->settings->forget('print.'.$key, $business)
                : $this->settings->set('print.'.$key, $key === 'accent' ? strtoupper($value) : $value, $business);
        }

        foreach (array_keys(PrintDesign::SWITCHES) as $key) {
            $this->settings->set('print.'.$key, (bool) ($data[$key] ?? false), $business);
        }

        $types = collect((array) ($data['types'] ?? []))
            ->map(fn ($type) => array_filter(['terms' => $this->text($type['terms'] ?? null), 'footer' => $this->text($type['footer'] ?? null)]))
            ->filter()
            ->all();

        $types === [] ? $this->settings->forget('print.types', $business) : $this->settings->set('print.types', $types, $business);

        return $this->success($business, __('body.branding_saved'));
    }

    /**
     * Path of a private branding image, for the signed-in preview.
     */
    public function privateImage(Business $business, string $kind): ?string
    {
        return in_array($kind, ['stamp', 'signature'], true) ? $this->settings->get("branding.{$kind}_path", null, $business) : null;
    }

    private function replaceImage(Business $business, string $key, ?UploadedFile $file, bool $remove, string $disk): void
    {
        $current = $this->settings->get('branding.'.$key, null, $business);

        if (($file || $remove) && $current) {
            UploadLibrary::delete($current, $disk);
            $this->settings->forget('branding.'.$key, $business);
        }

        if ($file) {
            $this->settings->set('branding.'.$key, UploadLibrary::store($file, 'branding', $disk), $business);
        }
    }

    /** Set, or forget when emptied (so defaults and fallbacks apply). */
    private function put(Business $business, string $key, mixed $value): void
    {
        $value = is_string($value) ? $this->text($value) : $value;

        $value === null || $value === ''
            ? $this->settings->forget('branding.'.$key, $business)
            : $this->settings->set('branding.'.$key, $value, $business);
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function color(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : null;
    }
}
