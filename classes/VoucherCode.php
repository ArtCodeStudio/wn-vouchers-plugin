<?php namespace JumpLink\Vouchers\Classes;

/**
 * Voucher code formatting + the signed redemption token the QR encodes.
 *
 * The human-readable code (MAM-100042-K) is for staff/phone; it is guessable
 * and sequential, so it is NOT what the QR carries. The QR encodes a signed
 * token (HMAC over the voucher id, keyed by the per-voucher secret + an app
 * pepper from env) so a leaked code cannot be forged into a redeem link.
 */
class VoucherCode
{
    const PREFIX = 'MAM';

    /** Mod-36 check character over the numeric part — catches typos at the till. */
    const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function format(int $number): string
    {
        return self::PREFIX . '-' . $number . '-' . self::checkChar($number);
    }

    public static function checkChar(int $number): string
    {
        return self::ALPHABET[$number % 36];
    }

    public static function isValid(string $code): bool
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^' . self::PREFIX . '-(\d+)-([0-9A-Z])$/', $code, $m)) {
            return false;
        }
        return self::checkChar((int) $m[1]) === $m[2];
    }

    //
    // Signed redemption token (for the QR)
    //

    public static function buildToken(int $voucherId, string $voucherSecret): string
    {
        $id  = self::b64urlEncode((string) $voucherId);
        $sig = self::b64urlEncode(hash_hmac('sha256', (string) $voucherId, self::key($voucherSecret), true));
        return $id . '.' . $sig;
    }

    /**
     * Verify a token. $secretResolver(int $voucherId): ?string returns the
     * voucher's token_secret. Returns the voucher id on success, null otherwise.
     */
    public static function verifyToken(string $token, callable $secretResolver): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        $voucherId = (int) self::b64urlDecode($parts[0]);
        if ($voucherId <= 0) {
            return null;
        }
        $secret = $secretResolver($voucherId);
        if (!$secret) {
            return null;
        }
        $expected = self::buildToken($voucherId, $secret);
        return hash_equals($expected, $token) ? $voucherId : null;
    }

    protected static function key(string $voucherSecret): string
    {
        return $voucherSecret . '|' . (string) env('VOUCHER_TOKEN_SECRET', '');
    }

    public static function b64urlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/'));
    }
}
