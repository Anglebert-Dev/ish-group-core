<?php

namespace App\Libraries;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

/**
 * RFC 6238 TOTP helpers for staff MFA (no third-party auth package required
 * for the TOTP math itself — bacon/bacon-qr-code is used only to render the
 * scannable QR code, which is infeasible to hand-roll reliably).
 */
class TotpLibrary
{
    public static function generateSecret(int $length = 16): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $secret;
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timeSlice = (int) floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::codeAt($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function currentCode(string $secret): string
    {
        return self::codeAt($secret, (int) floor(time() / 30));
    }

    public static function otpAuthUrl(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$account);
        $issuerParam = rawurlencode($issuer);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerParam}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Render an otpauth:// URL as a scannable QR code, returned as a data:
     * URI so it can be dropped straight into an <img src="..."> with no
     * extra route or temp file needed.
     */
    public static function qrCodeDataUri(string $otpauthUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(240, 1),
            new SvgImageBackEnd()
        );

        $svg = (new Writer($renderer))->writeString($otpauthUrl);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private static function codeAt(string $secret, int $timeSlice): string
    {
        $secretKey = self::base32Decode(Str::upper($secret));
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = preg_replace('/[^A-Z2-7]/', '', Str::upper($secret)) ?? '';
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $val = strpos($alphabet, $secret[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}
