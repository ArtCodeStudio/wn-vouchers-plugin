<?php namespace JumpLink\Vouchers\Classes;

/**
 * QR generation (M1). Encodes the signed redemption-lookup URL
 * (/api/jumplink/vouchers/scan?t=<token>) built via VoucherCode::buildToken —
 * NOT the bare voucher code. Returns a PNG data-URI for embedding in the PDF.
 * Requires composer dep `endroid/qr-code` at the app level.
 */
class QrService
{
    /** The redeem-lookup URL the QR should encode for a given voucher. */
    public static function scanUrl(int $voucherId, string $voucherSecret): string
    {
        $token = VoucherCode::buildToken($voucherId, $voucherSecret);
        return url('/api/jumplink/vouchers/scan') . '?t=' . urlencode($token);
    }

    // public static function dataUri(string $payload): string { /* M1: endroid/qr-code PNG */ }
}
