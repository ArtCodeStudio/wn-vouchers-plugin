<?php namespace JumpLink\Vouchers\Classes;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QR generation. Encodes the signed redemption-lookup URL
 * (/api/jumplink/vouchers/scan?t=<token>), built via VoucherCode::buildToken —
 * NOT the bare voucher code. A leaked sequential code is therefore useless; the
 * QR only opens an authenticated lookup, it never debits anything by itself.
 *
 * Requires composer dep `endroid/qr-code` (v6) at the app level.
 */
class QrService
{
    /** The redeem-lookup URL the QR should encode for a given voucher. */
    public static function scanUrl(int $voucherId, string $voucherSecret): string
    {
        $token = VoucherCode::buildToken($voucherId, $voucherSecret);
        return url('/api/jumplink/vouchers/scan') . '?t=' . urlencode($token);
    }

    /** A PNG data-URI for the payload, ready to embed as an <img src>. */
    public static function dataUri(string $payload, int $size = 300): string
    {
        $result = (new Builder(
            writer: new PngWriter(),
            data: $payload,
            size: $size,
            margin: 10,
        ))->build();

        return $result->getDataUri();
    }
}
