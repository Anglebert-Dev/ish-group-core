<?php

namespace App\Services\Branding;

use App\Libraries\UploadLibrary;
use App\Models\Business\Business;
use App\Services\BaseService;
use App\Support\Branding\Brand;
use App\Support\Settings\BusinessSettings;
use Illuminate\Http\UploadedFile;

/**
 * A business's branding (docs/branding-print-build-guide.md §3). Colour, logo
 * and favicon stay on the business; the rest lives in BusinessSettings under
 * "branding.*" (each change audited there). Stamp and signature are private
 * files (local disk), never public URLs.
 */
class BrandingService extends BaseService
{
    /** Fonts dompdf ships, so PDFs look the same on every server. */
    public const FONTS = [
        'sans' => 'DejaVu Sans',
        'serif' => 'DejaVu Serif',
        'helvetica' => 'Helvetica',
        'times' => 'Times',
    ];

    public const PAYMENT_TYPES = ['bank', 'mobile'];

    public const MAX_PAYMENT_METHODS = 6;

    /** Text fields of the Business details tab. */
    public const DETAILS = ['legal_name', 'tax_id', 'registration_no', 'address', 'phone', 'website'];

    public function __construct(protected BusinessSettings $settings)
    {
    }

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
            font: array_key_exists((string) $get('font'), self::FONTS) ? (string) $get('font') : 'sans',
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

        foreach (array_keys(\App\Support\Printing\PrintDesign::SWITCHES) as $key) {
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
