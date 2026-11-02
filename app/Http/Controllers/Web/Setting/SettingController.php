<?php

namespace App\Http\Controllers\Web\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Services\Branding\BrandingService;
use App\Services\Printing\PrintService;
use App\Services\Setting\SettingService;
use App\Support\Branding\Fonts;
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
    public const TABS = ['details', 'look', 'stamp', 'payments', 'documents'];

    public function __construct(
        protected SettingService $settings,
        protected BrandingService $branding,
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
            default => $this->legacy(app(UpdateSettingRequest::class)),
        };

        return redirect()->route('settings.index', in_array($section, self::TABS, true) ? ['tab' => $section] : [])
            ->with($result['status'] ? 'success' : 'error', $result['message']);
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
            'favicon' => ['nullable', 'image', 'max:1024'],
        ]);

        return $this->branding->saveLook($this->settings->current(), $data, $request->file('logo'), $request->file('logo_dark'), $request->file('favicon'), $request->boolean('remove_logo_dark'));
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
