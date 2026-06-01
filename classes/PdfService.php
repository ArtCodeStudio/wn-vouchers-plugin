<?php namespace JumpLink\Vouchers\Classes;

/**
 * Voucher PDF rendering (M1). Renders views/pdf/voucher.htm via
 * barryvdh/laravel-dompdf and embeds the QR (QrService) as a data-URI.
 * Requires composer deps `barryvdh/laravel-dompdf` + `endroid/qr-code` at the
 * app level. See voucher-plugin-spec.md §5.
 */
class PdfService
{
    // public static function render(Voucher $voucher): string { /* M1: return PDF bytes */ }
}
