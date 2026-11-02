<?php

namespace App\Support\Branding;

use Illuminate\Support\Facades\Storage;

/**
 * Everything printed about a business: who it is, how it looks, its stamp
 * and signature, how to pay it. Built by BrandingService; read by the print
 * engine, exports and emails. docs/branding-print-build-guide.md §3.
 */
final class Brand
{
    /**
     * @param  list<array{type: string, label: ?string, account_name: ?string, number: string, provider: ?string, branch: ?string, swift: ?string}>  $paymentMethods
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $legalName,
        public readonly ?string $taxId,
        public readonly ?string $registrationNo,
        public readonly ?string $address,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $website,
        public readonly string $primaryColor,
        public readonly string $secondaryColor,
        public readonly string $font,
        public readonly ?string $logoPath,
        public readonly ?string $logoDarkPath,
        public readonly ?string $stampPath,
        public readonly ?string $signaturePath,
        public readonly ?string $signatoryName,
        public readonly ?string $signatoryTitle,
        public readonly array $paymentMethods,
    ) {
    }

    /** The name on documents: the legal name when there is one. */
    public function documentName(): string
    {
        return $this->legalName ?: $this->name;
    }

    /**
     * Contact and registration lines, in print order, empty ones left out.
     *
     * @return list<string>
     */
    public function contactLines(): array
    {
        return array_values(array_filter([
            $this->address,
            implode(' · ', array_filter([$this->phone, $this->email, $this->website])) ?: null,
            implode(' · ', array_filter([
                $this->taxId ? __('body.brand_tax_id_short').': '.$this->taxId : null,
                $this->registrationNo ? __('body.brand_registration_short').': '.$this->registrationNo : null,
            ])) ?: null,
        ]));
    }

    /** Images for PDFs are embedded, never fetched (dompdf runs offline). */
    public function logoDataUri(bool $dark = false): ?string
    {
        return self::dataUri($dark ? ($this->logoDarkPath ?: $this->logoPath) : $this->logoPath, 'public');
    }

    public function stampDataUri(): ?string
    {
        return self::dataUri($this->stampPath, 'local');
    }

    public function signatureDataUri(): ?string
    {
        return self::dataUri($this->signaturePath, 'local');
    }

    public static function dataUri(?string $path, string $disk): ?string
    {
        if (! $path || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $mime = Storage::disk($disk)->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk($disk)->get($path));
    }
}
