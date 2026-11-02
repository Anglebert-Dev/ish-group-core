<?php

namespace App\Http\Controllers\Web\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Libraries\ColorLibrary;
use App\Models\Branding\BrandingVersion;
use App\Models\Business\Business;
use App\Services\Branding\BrandingService;
use App\Services\Branding\ThemeService;
use App\Services\Printing\PrintService;
use App\Services\Setting\SettingService;
use App\Support\Branding\Fonts;
use App\Support\Branding\LoginBranding;
use App\Support\Branding\ThemeSchema;
use App\Support\Branding\ThemeTokens;
use App\Support\Locale\Locales;
use App\Support\Mail\BrandedMail;
use App\Support\Printing\PrintDesign;
use App\Support\Printing\PrintRegistry;
use App\Support\Printing\SampleDocument;
use App\Support\Settings\BusinessSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings → Branding (docs/branding-print-build-guide.md §3): one page,
 * one tab per part of the brand; each tab saves only its own fields.
 */
class SettingController extends Controller
{
    public const TABS = ['details', 'look', 'theme', 'layout', 'login', 'stamp', 'payments', 'documents', 'history'];

    public function __construct(
        protected SettingService $settings,
        protected BrandingService $branding,
        protected ThemeService $themes,
    ) {}

    public function index(Request $request): View
    {
        $business = $this->settings->current();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'details';

        return view('app.settings.index', [
            'setting' => $business,
            'brand' => $this->branding->brand($business),
            'tab' => $tab,
            'tabs' => self::TABS,
            'fonts' => Fonts::options(),
            'design' => PrintDesign::for($business),
            // Blank = documents use the system font.
            'printFont' => array_key_exists((string) app(BusinessSettings::class)->get('print.font', null, $business), Fonts::LIST) ? app(BusinessSettings::class)->get('print.font', null, $business) : '',
            'documentTypes' => ['list' => __('body.print_type_list')] + app(PrintRegistry::class)->labels(),
            'draft' => $this->themes->draft($business),
            'live' => $this->themes->published($business),
            'theme' => $this->themes->effective($business, withDraft: true),
            'themeDefaults' => ThemeTokens::resolve($business->brandColor(), $this->themes->effective($business)['colors'] ?? []),
            'themeDarkDefaults' => ThemeTokens::resolve($business->brandColor(), $this->themes->effective($business)['colors_dark'] ?? [], dark: true),
            'contrast' => $tab === 'theme' ? $this->themes->contrast($business) : [],
            'loginPage' => $this->branding->loginPage($business) + ['suggested' => $tab === 'login' ? $this->branding->suggestHandle($business) : null],
            'brandOnScreen' => ['light' => $this->themes->tokens($business), 'dark' => $this->themes->tokens($business, dark: true)],
            'versions' => $tab === 'history' ? $this->themes->history($business) : collect(),
            'previewing' => $this->branding->previewing($business),
            'extraLogos' => collect(BrandingService::EXTRA_LOGOS)->mapWithKeys(fn ($field, $key) => [$field => $this->branding->logoFor($business, str_replace(['logo_', '_path'], '', $key))])->all(),
            'details' => collect(['short_name', 'slogan', 'description', 'locale'])->mapWithKeys(fn ($key) => [$key => $this->branding->detail($business, $key)])->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $business = $this->settings->current();
        $section = (string) $request->input('section');

        $result = match ($section) {
            'details' => $this->branding->saveDetails($business, $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'legal_name' => ['nullable', 'string', 'max:255'],
                'short_name' => ['nullable', 'string', 'max:30'],
                'slogan' => ['nullable', 'string', 'max:120'],
                'description' => ['nullable', 'string', 'max:500'],
                'locale' => ['nullable', Rule::in(array_keys(Locales::available()))],
                'tax_id' => ['nullable', 'string', 'max:64'],
                'registration_no' => ['nullable', 'string', 'max:64'],
                'address' => ['nullable', 'string', 'max:500'],
                'phone' => ['nullable', 'string', 'max:64'],
                'website' => ['nullable', 'string', 'max:255'],
            ])),
            'look' => $this->saveLook($request),
            'stamp' => $this->branding->saveStampAndSignature($business, $request->validate([
                'stamp' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
                'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
                'signatory_name' => ['nullable', 'string', 'max:255'],
                'signatory_title' => ['nullable', 'string', 'max:255'],
            ]), $request->file('stamp'), $request->file('signature'), $request->boolean('remove_stamp'), $request->boolean('remove_signature')),
            'payments' => $this->branding->savePaymentMethods($business, $request->validate([
                'methods' => ['array', 'max:'.BrandingService::MAX_PAYMENT_METHODS],
                'methods.*.type' => ['nullable', Rule::in(BrandingService::PAYMENT_TYPES)],
                'methods.*.label' => ['nullable', 'string', 'max:100'],
                'methods.*.account_name' => ['nullable', 'string', 'max:255'],
                'methods.*.number' => ['nullable', 'string', 'max:64'],
                'methods.*.provider' => ['nullable', 'string', 'max:100'],
                'methods.*.branch' => ['nullable', 'string', 'max:100'],
                'methods.*.swift' => ['nullable', 'string', 'max:20'],
            ])['methods'] ?? []),
            'documents' => $this->branding->saveDesign($business, $this->designInput($request)),
            'theme' => $this->saveTheme($request, $business),
            'layout' => $this->saveLayout($request, $business),
            'login' => $this->saveLoginPage($request, $business),
            default => $this->legacy(app(UpdateSettingRequest::class)),
        };

        return redirect()->route('settings.index', in_array($section, self::TABS, true) ? ['tab' => $section] : [])
            ->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function publishTheme(Request $request): RedirectResponse
    {
        // Published or thrown away, there is no draft left to preview.
        $request->session()->forget(ThemeService::PREVIEW);

        return $this->back($this->themes->publish($this->settings->current()));
    }

    public function discardTheme(Request $request): RedirectResponse
    {
        $request->session()->forget(ThemeService::PREVIEW);

        return $this->back($this->themes->discard($this->settings->current()));
    }

    public function resetTheme(): RedirectResponse
    {
        return $this->back($this->themes->reset($this->settings->current()), 'theme');
    }

    public function restoreTheme(BrandingVersion $version): RedirectResponse
    {
        return $this->back($this->themes->restore($this->settings->current(), $version), 'theme');
    }

    /** Show the whole app with the draft, for this user only, until stopped. */
    public function previewTheme(Request $request): RedirectResponse
    {
        $business = $this->settings->current();
        $request->session()->put(ThemeService::PREVIEW, $business->id);
        audit('branding_previewed', 'Started previewing the branding draft', null, [], $business->id);

        return redirect()->route('dashboard');
    }

    public function stopPreview(Request $request): RedirectResponse
    {
        $request->session()->forget(ThemeService::PREVIEW);

        return redirect()->route('settings.index', ['tab' => 'theme']);
    }

    private function saveTheme(Request $request, Business $business): array
    {
        // Hex, rgb() or hsl(); stored as hex.
        $color = fn (string $attribute, mixed $value, \Closure $fail) => blank($value) || ColorLibrary::parse((string) $value) || $fail(__('body.theme_bad_color'));

        $data = $request->validate([
            'colors' => ['array'],
            'colors.*' => ['nullable', 'string', 'max:40', $color],
            'colors_dark' => ['array'],
            'colors_dark.*' => ['nullable', 'string', 'max:40', $color],
            'mode' => ['nullable', Rule::in(ThemeTokens::MODES)],
        ]);

        // A part not on the form keeps what the draft has.
        return $this->themes->saveDraft($business, array_filter([
            'colors' => $data['colors'] ?? [],
            'colors_dark' => $request->has('colors_dark') ? $data['colors_dark'] : null,
            'mode' => $data['mode'] ?? null,
            'user_mode' => $request->has('user_mode') ? $request->boolean('user_mode') : null,
        ], fn ($value) => $value !== null));
    }

    /** Studio preview: one of every component. */
    public function studioComponents(): View
    {
        return view('app.settings.studio.components');
    }

    /** Studio preview: the login page as this business's staff see it. */
    public function studioLogin(): Response
    {
        return response(view('auth.login', ['registrationIsOpen' => false, 'isFirstRegistration' => false])->render());
    }

    /** Studio preview: a sample email. */
    public function studioEmail(): Response
    {
        $business = $this->settings->current();

        return response((string) BrandedMail::make($business, __('body.studio_email_subject'), [__('body.studio_email_line')], __('body.view'), url('/'))->render());
    }

    private function saveLoginPage(Request $request, Business $business): array
    {
        $request->merge(['login_handle' => strtolower(trim((string) $request->input('login_handle')))]);
        $data = $request->validate([
            'login_handle' => ['required', 'string', 'regex:'.LoginBranding::HANDLE_PATTERN, function ($attribute, $value, $fail) use ($business) {
                if (app(LoginBranding::class)->handleTaken($value, $business)) {
                    $fail(__('body.login_handle_taken'));
                }
            }],
            'login_heading' => ['nullable', 'string', 'max:120'],
            'login_welcome' => ['nullable', 'string', 'max:300'],
            'login_panel_text' => ['nullable', 'string', 'max:200'],
            'login_footer' => ['nullable', 'string', 'max:300'],
            'login_show_contact' => ['boolean'],
            'login_image' => ['nullable', 'image', 'max:4096'],
        ]);

        return $this->branding->saveLoginPage($business, $data, $request->file('login_image'), $request->boolean('remove_login_image'));
    }

    private function saveLayout(Request $request, Business $business): array
    {
        $rules = collect(ThemeSchema::LAYOUT)->mapWithKeys(fn ($allowed, $key) => ["layout.{$key}" => ['required', Rule::in($allowed)]])->all();
        $data = $request->validate($rules + ['heading_font' => ['required', Rule::in(['same', ...array_keys(Fonts::LIST)])]]);

        return $this->themes->saveDraft($business, [
            'layout' => $data['layout'],
            'heading_font' => $data['heading_font'] === 'same' ? null : $data['heading_font'],
            'switches' => collect(ThemeSchema::SWITCHES)->mapWithKeys(fn ($default, $key) => [$key => $request->boolean("switches.{$key}")])->all(),
        ]);
    }

    private function back(array $result, ?string $tab = null): RedirectResponse
    {
        $tab ??= in_array(request('tab'), self::TABS, true) ? request('tab') : 'theme';

        return redirect()->route('settings.index', ['tab' => $tab])->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    /**
     * The designer's live preview: the sample invoice with the (unsaved)
     * choices in the query string. ?format=pdf shows the real PDF.
     */
    public function preview(Request $request, PrintService $printer): Response
    {
        $business = $this->settings->current();
        $design = PrintDesign::for($business, null, $this->designInput($request, forPreview: true));
        $document = SampleDocument::make();

        return $request->query('format') === 'pdf'
            ? $printer->pdf($document, $business, 'preview', inline: true, design: $design)
            : response($printer->html($document, $business, null, autoPrint: false, design: $design)->render());
    }

    /**
     * Design choices from the form (saved) or the query string (preview).
     *
     * @return array<string, mixed>
     */
    private function designInput(Request $request, bool $forPreview = false): array
    {
        $data = $request->validate([
            'layout' => ['nullable', Rule::in(PrintDesign::LAYOUTS)],
            'paper' => ['nullable', Rule::in(array_keys(PrintDesign::PAPERS))],
            'accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font' => ['nullable', Rule::in(array_keys(Fonts::LIST))],
            'logo_position' => ['nullable', Rule::in(PrintDesign::LOGO_POSITIONS)],
            'logo_size' => ['nullable', Rule::in(array_keys(PrintDesign::LOGO_SIZES))],
            'header_note' => ['nullable', 'string', 'max:200'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'types' => ['array'],
            'types.*.terms' => ['nullable', 'string', 'max:2000'],
            'types.*.footer' => ['nullable', 'string', 'max:500'],
        ]);

        foreach (array_keys(PrintDesign::SWITCHES) as $switch) {
            $data[$switch] = $request->boolean($switch);
        }

        if ($forPreview) {
            // The sample shows the invoice terms/footer being typed.
            $data['types'] = ['sample' => $data['types']['sales_invoice'] ?? []];
            $data = array_filter($data, fn ($value) => $value !== null);
        }

        return $data;
    }

    /**
     * The private stamp/signature, for the preview on this page only.
     */
    public function image(string $kind): Response
    {
        $path = $this->branding->privateImage($this->settings->current(), $kind);

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($path) ?: 'image/png',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function saveLook(Request $request): array
    {
        $request->merge(array_filter([
            'brand_color' => $request->filled('brand_color') ? strtoupper((string) $request->input('brand_color')) : null,
            'secondary_color' => $request->filled('secondary_color') ? strtoupper((string) $request->input('secondary_color')) : null,
        ]));

        $data = $request->validate([
            'brand_color' => ['required', 'string', 'regex:/^#[0-9A-F]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/'],
            'font' => ['nullable', Rule::in(array_keys(Fonts::LIST))],
            'logo' => ['nullable', 'image', 'max:4096'],
            'logo_dark' => ['nullable', 'image', 'max:4096'],
            'logo_login' => ['nullable', 'image', 'max:4096'],
            'logo_mobile' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:1024'],
        ]);

        $business = $this->settings->current();
        $this->branding->saveExtraLogos($business,
            ['logo_login' => $request->file('logo_login'), 'logo_mobile' => $request->file('logo_mobile')],
            ['logo_login' => $request->boolean('remove_logo_login'), 'logo_mobile' => $request->boolean('remove_logo_mobile')]);

        return $this->branding->saveLook($business, $data, $request->file('logo'), $request->file('logo_dark'), $request->file('favicon'), $request->boolean('remove_logo_dark'));
    }

    /** The original one-form settings (name, email, colour, logo, favicon). */
    private function legacy(UpdateSettingRequest $request): array
    {
        return $this->settings->update(
            $this->settings->current(),
            $request->safe()->except(['logo', 'favicon']),
            $request->file('logo'),
            $request->file('favicon'),
        );
    }
}
