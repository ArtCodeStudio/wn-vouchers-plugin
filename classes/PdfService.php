<?php namespace JumpLink\Vouchers\Classes;

use View;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\Settings;

/**
 * Voucher PDF rendering. Renders the Blade template views/pdf/voucher.blade.php
 * (via barryvdh/laravel-dompdf) and embeds the QR (QrService) as a data-URI.
 *
 * Requires composer deps `barryvdh/laravel-dompdf` (v3) + `endroid/qr-code` (v6)
 * at the app level. The template is resolved by absolute path (View::file) so it
 * works regardless of plugin view-namespace registration.
 */
class PdfService
{
    /** Render the voucher PDF and return the raw bytes (starts with "%PDF"). */
    public static function render(Voucher $voucher): string
    {
        $qr = '';
        try {
            $qr = QrService::dataUri(QrService::scanUrl((int) $voucher->id, (string) $voucher->token_secret));
        } catch (\Throwable $e) {
            // QR is best-effort; the human-readable code is always printed.
        }

        $html = View::file(dirname(__DIR__) . '/views/pdf/voucher.blade.php', [
            'voucher'    => $voucher,
            'qr'         => $qr,
            'brand_name' => Settings::get('brand_name'),
            'accent'     => Settings::get('pdf_accent_color', '#1a3a5a'),
            'footer'     => Settings::get('pdf_footer_text'),
        ])->render();

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->output();
    }
}
