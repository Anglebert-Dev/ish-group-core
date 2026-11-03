<?php

namespace Modules\InjongePay\Services;

use App\Models\Business\Business;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\InjongePay\Exceptions\InjongePayException;
use Modules\InjongePay\Models\Wallet;

/**
 * Many merchant codes for one business from a CSV (one row per till or
 * branch). Every row is checked first; if one is wrong, nothing is created.
 * Registration at GTBank then goes out from the queue a few at a time.
 */
class WalletImport
{
    public const MAX_ROWS = 1000;

    // Seconds between two registrations reaching GTBank.
    public const SPACING = 3;

    private const COLUMNS = [
        'name' => ['name', 'wallet', 'till', 'branch', 'merchant account'],
        'kyc_business_owner' => ['owner', 'business owner', 'representative'],
        'kyc_tin' => ['tin'],
        'kyc_contact_phone' => ['phone', 'contact phone'],
        'kyc_contact_email' => ['email', 'contact email'],
    ];

    public function __construct(protected MerchantService $merchants)
    {
    }

    /**
     * @return array{rows: list<array>, errors: array<int, list<string>>}  line number => problems
     */
    public function read(string $path, Business $business): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new InjongePayException(__('injongepay::injongepay.err_statement_read'));
        }

        $header = fgetcsv($handle, 0, $this->delimiter($path));
        $map = $this->columns(array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), $header ?: []));

        if (! isset($map['name'])) {
            fclose($handle);

            throw new InjongePayException(__('injongepay::injongepay.err_import_name_column'));
        }

        $rows = [];
        $errors = [];
        $line = 1;
        $existing = Wallet::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('business_id', $business->id)->pluck('name')->map(fn ($n) => mb_strtolower($n))->flip();
        $seen = [];

        while (($cells = fgetcsv($handle, 0, $this->delimiter($path))) !== false) {
            $line++;

            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($map as $field => $index) {
                $value = trim((string) ($cells[$index] ?? ''));
                $row[$field] = $value === '' ? null : $value;
            }

            if ($row['kyc_contact_phone'] ?? null) {
                $row['kyc_contact_phone'] = $this->phone($row['kyc_contact_phone']);
            }

            $problems = Validator::make($row, [
                'name' => ['required', 'string', 'max:120'],
                'kyc_business_owner' => ['nullable', 'string', 'max:255'],
                'kyc_tin' => ['nullable', 'digits:9'],
                'kyc_contact_phone' => ['nullable', 'regex:/^\+250\d{9}$/'],
                'kyc_contact_email' => ['nullable', 'email', 'max:255'],
            ], ['kyc_contact_phone.regex' => __('injongepay::injongepay.err_phone_format')])->errors()->all();

            $key = mb_strtolower((string) $row['name']);
            if ($row['name'] && (isset($existing[$key]) || isset($seen[$key]))) {
                $problems[] = __('injongepay::injongepay.err_import_duplicate', ['name' => $row['name']]);
            }
            $seen[$key] = true;

            if ($problems) {
                $errors[$line] = $problems;
            }

            $rows[] = $row + ['line' => $line];

            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);

                throw new InjongePayException(__('injongepay::injongepay.err_import_too_many', ['max' => self::MAX_ROWS]));
            }
        }

        fclose($handle);

        if ($rows === []) {
            throw new InjongePayException(__('injongepay::injongepay.err_import_empty'));
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** All rows or none. @return Collection<int, Wallet> */
    public function create(Business $business, array $rows): Collection
    {
        $wallets = DB::transaction(fn () => collect($rows)->values()->map(fn (array $row, int $i) => $this->merchants->create($business, $row, $i * self::SPACING)));

        audit('injongepay_wallets_imported', __('injongepay::injongepay.audit_wallets_imported', ['count' => $wallets->count(), 'business' => $business->name]),
            null, ['count' => $wallets->count(), 'from' => $wallets->first()?->merchant_code, 'to' => $wallets->last()?->merchant_code], $business->id);

        return $wallets;
    }

    /** "0788 123 456", "250788123456" → "+250788123456". */
    private function phone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        return match (true) {
            (bool) preg_match('/^07\d{8}$/', $digits) => '+250'.substr($digits, 1),
            (bool) preg_match('/^2507\d{8}$/', $digits) => '+'.$digits,
            default => $value,
        };
    }

    private function columns(array $headings): array
    {
        $map = [];
        foreach (self::COLUMNS as $field => $names) {
            foreach ($headings as $i => $heading) {
                if (in_array($heading, $names, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    private function delimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $first = (string) fgets($handle);
        fclose($handle);

        return substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    }
}
