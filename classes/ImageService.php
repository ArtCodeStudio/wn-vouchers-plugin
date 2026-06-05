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
        // Portrait card — the voucher is digital and most buyers save it to a
        // phone, where portrait fills the screen and reads better than landscape.
        $W = 760; $H = 980; $pad = 56; $border = 16;
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

        $dir  = self::fontDir();
        $bold = $dir . 'DejaVuSans-Bold.ttf';
        $reg  = $dir . 'DejaVuSans.ttf';
        $mono = $dir . 'DejaVuSansMono-Bold.ttf';
        $inner = $W - 2 * $pad;

        // Brand, centered at the top.
        if (!empty($settings->brand_name)) {
            self::centered($img, self::fitSize($settings->brand_name, $bold, 24, $inner), $bold, $acc, 115, $settings->brand_name, $W);
        }

        // QR, large and centered (scanned at the till).
        $qrSize = 340;
        if ($qr = self::voucherQr($voucher)) {
            imagecopyresampled($img, $qr, (int) (($W - $qrSize) / 2), 165, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }

        // Hero value + code, centered.
        $value = trans('jumplink.vouchers::lang.voucher_card.value_over', ['value' => $voucher->initial_value_euro]);
        self::centered($img, self::fitSize($value, $bold, 42, $inner), $bold, $dark, 625, $value, $W);
        self::centered($img, 24, $mono, $acc, 695, $voucher->code, $W);

        // Optional recipient + validity, centered below the code.
        $y = 770;
        if ($voucher->recipient_name) {
            $t = trans('jumplink.vouchers::lang.voucher_card.for', ['name' => $voucher->recipient_name]);
            self::centered($img, self::fitSize($t, $reg, 17, $inner), $reg, $muted, $y, $t, $W);
            $y += 44;
        }
        if ($voucher->valid_until) {
            $t = trans('jumplink.vouchers::lang.voucher_card.valid_until', ['date' => $voucher->valid_until->format('d.m.Y')]);
            self::centered($img, self::fitSize($t, $reg, 17, $inner), $reg, $muted, $y, $t, $W);
        }

        // Till hint + optional footer, pinned near the bottom edge.
        $hint = trans('jumplink.vouchers::lang.voucher_card.till_hint');
        self::centered($img, self::fitSize($hint, $reg, 15, $inner), $reg, $muted, $H - 115, $hint, $W);
        if (!empty($settings->pdf_footer_text)) {
            self::centered($img, self::fitSize($settings->pdf_footer_text, $reg, 13, $inner), $reg, $muted, $H - 70, $settings->pdf_footer_text, $W);
        }

        ob_start();
        imagepng($img);
        $blob = ob_get_clean();
        imagedestroy($img);
        return $blob;
    }

    /** Draw $text horizontally centered across width $W, at baseline $y. */
    protected static function centered($img, int $size, string $font, int $color, int $y, string $text, int $W): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $width = $box[2] - $box[0];
        $x = (int) (($W - $width) / 2 - $box[0]);
        imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
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
