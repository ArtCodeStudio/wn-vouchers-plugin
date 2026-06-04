<?php namespace JumpLink\Vouchers\Classes;

use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\Settings;

/**
 * Voucher PNG rendering. The voucher is delivered as an image, not a PDF —
 * every phone opens a PNG, whereas some cannot open a PDF.
 *
 * Drawn natively with GD (FreeType) using the DejaVu fonts that dompdf already
 * bundles, so it needs no Ghostscript, no Imagick PDF policy and no system
 * fonts — it works on any host with the standard GD extension.
 */
class ImageService
{
    /** Whether PNG rendering is possible on this host. */
    public static function isAvailable(): bool
    {
        if (!function_exists('imagettftext') || !function_exists('imagepng')) {
            return false;
        }
        $gd = gd_info();
        return !empty($gd['FreeType Support']) && !empty($gd['PNG Support']);
    }

    /** Render the voucher as PNG bytes (a card image). */
    public static function render(Voucher $voucher): string
    {
        $W = 1000; $H = 560; $pad = 56; $border = 16;
        $settings = Settings::instance();

        $img = imagecreatetruecolor($W, $H);
        imagealphablending($img, true);
        $white = imagecolorallocate($img, 255, 255, 255);
        $dark  = imagecolorallocate($img, 34, 34, 34);
        $muted = imagecolorallocate($img, 85, 85, 85);
        [$r, $g, $b] = self::hexColorOr($settings->pdf_accent_color, [26, 58, 90]); // #1a3a5a
        $acc = imagecolorallocate($img, $r, $g, $b);
        imagefilledrectangle($img, 0, 0, $W, $H, $white);

        // Full-page "Briefpapier" background, or an accent frame.
        if ($bg = self::attachmentImage($settings->pdf_background ?? null)) {
            imagecopyresampled($img, $bg, 0, 0, 0, 0, $W, $H, imagesx($bg), imagesy($bg));
            imagedestroy($bg);
        } else {
            imagesetthickness($img, 3);
            imagerectangle($img, $border, $border, $W - $border, $H - $border, $acc);
            imagesetthickness($img, 1);
        }

        // QR top-right (drawn first; the value sits below it, so it never collides).
        $qrSize = 180;
        if ($qr = self::voucherQr($voucher)) {
            imagecopyresampled($img, $qr, $W - $pad - $qrSize, $pad, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }

        $dir  = self::fontDir();
        $bold = $dir . 'DejaVuSans-Bold.ttf';
        $reg  = $dir . 'DejaVuSans.ttf';
        $mono = $dir . 'DejaVuSansMono-Bold.ttf';
        $x = $pad;

        if (!empty($settings->brand_name)) {
            imagettftext($img, 19, 0, $x, 112, $acc, $bold, $settings->brand_name);
        }

        $value = 'Gutschein über ' . $voucher->initial_value_euro;
        imagettftext($img, self::fitSize($value, $bold, 34, $W - 2 * $pad), 0, $x, 300, $dark, $bold, $value);
        imagettftext($img, 22, 0, $x, 360, $acc, $mono, $voucher->code);

        $y = 408;
        if ($voucher->recipient_name) {
            imagettftext($img, 14, 0, $x, $y, $muted, $reg, 'Für: ' . $voucher->recipient_name);
            $y += 38;
        }
        if ($voucher->valid_until) {
            imagettftext($img, 14, 0, $x, $y, $muted, $reg, 'Gültig bis ' . $voucher->valid_until->format('d.m.Y'));
            $y += 38;
        }
        imagettftext($img, 13, 0, $x, $y, $muted, $reg, 'An der Kasse vorzeigen – ein Restguthaben bleibt erhalten.');
        $y += 34;
        if (!empty($settings->pdf_footer_text)) {
            imagettftext($img, 12, 0, $x, $y, $muted, $reg, $settings->pdf_footer_text);
        }

        ob_start();
        imagepng($img);
        $blob = ob_get_clean();
        imagedestroy($img);
        return $blob;
    }

    /** Largest font size (<= $max) at which $text fits within $maxWidth px. */
    protected static function fitSize(string $text, string $font, int $max, int $maxWidth): int
    {
        for ($s = $max; $s > 14; $s--) {
            $box = imagettfbbox($s, 0, $font, $text);
            if (abs($box[2] - $box[0]) <= $maxWidth) {
                return $s;
            }
        }
        return 14;
    }

    /** Parse "#rrggbb" to [r,g,b], or the given default. */
    protected static function hexColorOr(?string $hex, array $default): array
    {
        if ($hex && preg_match('/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', trim($hex), $m)) {
            return [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
        }
        return $default;
    }

    /** The DejaVu font directory (dompdf's bundled copy, or the system one). */
    protected static function fontDir(): string
    {
        foreach ([base_path('vendor/dompdf/dompdf/lib/fonts/'), '/usr/share/fonts/truetype/dejavu/'] as $dir) {
            if (is_file($dir . 'DejaVuSans.ttf')) {
                return $dir;
            }
        }
        return base_path('vendor/dompdf/dompdf/lib/fonts/');
    }

    /** The voucher's signed QR as a GD image, or null (QR is best-effort). */
    protected static function voucherQr(Voucher $voucher)
    {
        try {
            $uri = QrService::dataUri(QrService::scanUrl((int) $voucher->id, (string) $voucher->token_secret));
            $bytes = base64_decode(substr($uri, strpos($uri, ',') + 1));
            return @imagecreatefromstring($bytes) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** A Settings file attachment (logo/background) as a GD image, or null. */
    protected static function attachmentImage($file)
    {
        if (!$file) {
            return null;
        }
        try {
            $bytes = $file->getContents();
            return ($bytes !== null && $bytes !== '') ? (@imagecreatefromstring($bytes) ?: null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
