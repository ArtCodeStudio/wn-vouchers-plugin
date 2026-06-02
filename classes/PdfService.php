<?php namespace JumpLink\Vouchers\Classes;

use Log;
use View;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\Settings;

/**
 * Voucher PDF rendering. Renders the Blade template views/pdf/voucher.blade.php
 * (via barryvdh/laravel-dompdf) and embeds the QR (QrService) as a data-URI.
 *
 * The shop can individualise the PDF in Settings → Vouchers → PDF: an optional
 * logo and a full-page "Briefpapier" background image (both embedded as data
 * URIs so dompdf needs no filesystem access), plus brand name, accent colour and
 * a footer note.
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

        $settings = Settings::instance();

        $html = View::file(dirname(__DIR__) . '/views/pdf/voucher.blade.php', [
            'voucher'    => $voucher,
            'qr'         => $qr,
            'brand_name' => $settings->brand_name,
            'accent'     => $settings->pdf_accent_color ?: '#1a3a5a',
            'footer'     => $settings->pdf_footer_text,
            'logo'       => self::imageDataUri($settings->pdf_logo),
            'background' => self::imageDataUri($settings->pdf_background),
        ])->render();

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    /** Read an attached image (logo/background) as a base64 data URI, or null. */
    protected static function imageDataUri($file): ?string
    {
        if (!$file) {
            return null;
        }
        try {
            $bytes = $file->getContents();
            if ($bytes === null || $bytes === '') {
                return null;
            }
            $mime = $file->content_type ?: 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode($bytes);
        } catch (\Throwable $e) {
            Log::error('[vouchers] could not load PDF image: ' . $e->getMessage());
            return null;
        }
    }
}
