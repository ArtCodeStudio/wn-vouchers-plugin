<?php namespace JumpLink\Vouchers\Classes;

use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\Settings;

/**
 * Voucher PNG rendering. The voucher is delivered as an image, not a PDF —
 * every phone opens a PNG, whereas some cannot open a PDF.
 *
 * Two layouts:
 *  - OVERLAY (when a full-page background image is configured): the background
 *    carries all the branding (logo, name, address, decoration) and we only
 *    overlay the QR, the voucher number (rotated, right of the QR) and the value
 *    in the coloured bottom strip. Positions are fractions of the canvas so they
 *    track the uploaded background's size/aspect.
 *  - CLASSIC (no background): a self-contained card with an accent frame, brand
 *    name, QR, value and code drawn on white.
 *
 * Drawn natively with GD (FreeType) using the DejaVu fonts that dompdf already
 * bundles, so it needs no Ghostscript, no Imagick PDF policy and no system
 * fonts — it works on any host with the standard GD extension.
 */
class ImageService
{
    /** Brand gold used for the number + value amount in the overlay layout. */
    const GOLD = [197, 166, 119]; // #C5A677

    /** Whether PNG rendering is possible on this host. */
    public static function isAvailable(): bool
    {
        if (!function_exists('imagettftext') || !function_exists('imagepng')) {
            return false;
        }
        $gd = gd_info();
        return !empty($gd['FreeType Support']) && !empty($gd['PNG Support']);
    }

    /** Render the voucher as PNG bytes. Overlay layout if a background is set, else classic. */
    public static function render(Voucher $voucher): string
    {
        $settings = Settings::instance();
        if ($bg = self::attachmentImage($settings->pdf_background ?? null)) {
            return self::renderOverlay($voucher, $settings, $bg);
        }
        return self::renderClassic($voucher, $settings);
    }

    /**
     * Overlay layout: full-bleed background + QR + rotated number + bottom value.
     * The canvas is the background's own size so the artwork is never distorted;
     * every element is placed as a fraction of that canvas.
     */
    protected static function renderOverlay(Voucher $voucher, $settings, $bg): string
    {
        $W = imagesx($bg);
        $H = imagesy($bg);

        $img = imagecreatetruecolor($W, $H);
        imagecopy($img, $bg, 0, 0, 0, 0, $W, $H);
        imagedestroy($bg);

        $dir  = self::fontDir();
        $bold = $dir . 'DejaVuSans-Bold.ttf';

        // QR: square, upper-left block (matches the 1197x2048 design at ~0.44 W).
        if ($qr = self::voucherQr($voucher)) {
            $side = (int) round($W * 0.438);
            $qx   = (int) round($W * 0.237);
            $qy   = (int) round($H * 0.160);
            imagecopyresampled($img, $qr, $qx, $qy, 0, 0, $side, $side, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }

        // Voucher number: rotated 90° (reads bottom-to-top), gold, right of the QR.
        $numSize = self::fitRotatedSize($voucher->code, $bold, 40, (int) round($H * 0.30));
        self::verticalText($img, $numSize, $bold, self::GOLD, (int) round($W * 0.752), (int) round($H * 0.313), $voucher->code);

        // Value in the bottom strip: "<amount> EUR" gold + " <label>" white, centered.
        $amount = self::valueAmount($voucher) . ' EUR ';
        $label  = trans('jumplink.vouchers::lang.voucher_card.amount_label');
        $vSize  = self::fitSize($amount . $label, $bold, 52, (int) ($W * 0.82));
        self::twoColorCentered($img, $vSize, $bold, self::GOLD, [255, 255, 255], $amount, $label, $W, (int) round($H * 0.978));

        ob_start();
        imagepng($img);
        $blob = ob_get_clean();
        imagedestroy($img);
        return $blob;
    }

    /** Classic self-contained card (used when no background image is configured). */
    protected static function renderClassic(Voucher $voucher, $settings): string
    {
        // Portrait card — the voucher is digital and most buyers save it to a
        // phone, where portrait fills the screen and reads better than landscape.
        $W = 760; $H = 980; $pad = 56; $border = 16;

        $img = imagecreatetruecolor($W, $H);
        imagealphablending($img, true);
        $white = imagecolorallocate($img, 255, 255, 255);
        $dark  = imagecolorallocate($img, 34, 34, 34);
        $muted = imagecolorallocate($img, 85, 85, 85);
        [$r, $g, $b] = self::hexColorOr($settings->pdf_accent_color, [26, 58, 90]); // #1a3a5a
        $acc = imagecolorallocate($img, $r, $g, $b);
        imagefilledrectangle($img, 0, 0, $W, $H, $white);

        imagesetthickness($img, 3);
        imagerectangle($img, $border, $border, $W - $border, $H - $border, $acc);
        imagesetthickness($img, 1);

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

    /** The voucher value as a compact amount string ("50", "49,50") — no currency. */
    protected static function valueAmount(Voucher $voucher): string
    {
        // initial_value_euro is already localised and may carry a currency symbol
        // (e.g. "50,00 €"); strip it and a trailing ",00" so we render "50 EUR".
        $v = trim(str_replace(['€', 'EUR'], '', (string) $voucher->initial_value_euro));
        $v = trim(preg_replace('/[,.]00$/', '', $v));
        return $v !== '' ? $v : (string) $voucher->initial_value_euro;
    }

    /** Draw $text horizontally centered across width $W, at baseline $y. */
    protected static function centered($img, int $size, string $font, int $color, int $y, string $text, int $W): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $width = $box[2] - $box[0];
        $x = (int) (($W - $width) / 2 - $box[0]);
        imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
    }

    /** Draw "$t1$t2" centered, $t1 in $rgb1 and $t2 in $rgb2, at baseline $y. */
    protected static function twoColorCentered($img, int $size, string $font, array $rgb1, array $rgb2, string $t1, string $t2, int $W, int $y): void
    {
        $b1 = imagettfbbox($size, 0, $font, $t1); $w1 = $b1[2] - $b1[0];
        $b2 = imagettfbbox($size, 0, $font, $t2); $w2 = $b2[2] - $b2[0];
        $x  = (int) (($W - ($w1 + $w2)) / 2);
        $c1 = imagecolorallocate($img, $rgb1[0], $rgb1[1], $rgb1[2]);
        $c2 = imagecolorallocate($img, $rgb2[0], $rgb2[1], $rgb2[2]);
        imagettftext($img, $size, 0, $x - $b1[0], $y, $c1, $font, $t1);
        imagettftext($img, $size, 0, $x + $w1 - $b2[0], $y, $c2, $font, $t2);
    }

    /** Draw $text rotated 90° (bottom-to-top), centered on the point ($cx,$cy). */
    protected static function verticalText($img, int $size, string $font, array $rgb, int $cx, int $cy, string $text): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $tw = $box[2] - $box[0];
        $th = $box[1] - $box[7];
        $pad = 8;
        $tmp = imagecreatetruecolor($tw + 2 * $pad, $th + 2 * $pad);
        imagesavealpha($tmp, true);
        $trans = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefill($tmp, 0, 0, $trans);
        $col = imagecolorallocate($tmp, $rgb[0], $rgb[1], $rgb[2]);
        imagettftext($tmp, $size, 0, $pad - $box[0], $pad - $box[7], $col, $font, $text);
        $rot = imagerotate($tmp, 90, $trans); // CCW → reads bottom-to-top
        imagesavealpha($rot, true);
        $rw = imagesx($rot); $rh = imagesy($rot);
        imagecopy($img, $rot, (int) ($cx - $rw / 2), (int) ($cy - $rh / 2), 0, 0, $rw, $rh);
        imagedestroy($tmp);
        imagedestroy($rot);
    }

    /** Largest font size (<= $max) at which the rotated text's length fits $maxLen px. */
    protected static function fitRotatedSize(string $text, string $font, int $max, int $maxLen): int
    {
        for ($s = $max; $s > 14; $s--) {
            $box = imagettfbbox($s, 0, $font, $text);
            if (abs($box[2] - $box[0]) <= $maxLen) {
                return $s;
            }
        }
        return 14;
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
