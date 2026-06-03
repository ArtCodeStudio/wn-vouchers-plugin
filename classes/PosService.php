<?php namespace JumpLink\Vouchers\Classes;

use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\VoucherOrder;

/**
 * Till (POS) helpers, kept out of the component so they are unit-testable.
 *
 * resolveVoucher() turns whatever the staff member entered or the camera scanned
 * — a human code (MAM-100042-K), a bare signed token (<id>.<sig>), or the full
 * scan URL (.../scan?t=<token>) — into the Voucher, verifying the token's HMAC.
 * sell() records an on-site sale as a new (paid) voucher.
 */
class PosService
{
    /** Resolve a voucher from a typed code, a signed token, or a scan URL. */
    public static function resolveVoucher(string $input): ?Voucher
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // A scan URL or bare token carries a dot-separated HMAC signature.
        $token = self::extractToken($input);
        if ($token !== null) {
            $voucherId = VoucherCode::verifyToken($token, function ($id) {
                $voucher = Voucher::find($id);
                return $voucher ? $voucher->token_secret : null;
            });
            return $voucherId ? Voucher::find($voucherId) : null;
        }

        // A full human-readable code (with the typo-catching check char) …
        $code = strtoupper($input);
        if (VoucherCode::isValid($code)) {
            return Voucher::where('code', $code)->first();
        }

        // … or just the bare voucher number that is printed on the card.
        if (ctype_digit($input)) {
            return Voucher::where('number', (int) $input)->first();
        }

        return null;
    }

    /** Record an on-site sale as a new, paid voucher. */
    public static function sell(array $input, ?int $userId = null): array
    {
        $valueCents = self::toCents($input['value_euro'] ?? ($input['value'] ?? 0));
        if ($valueCents <= 0) {
            return ['success' => false, 'error' => 'Bitte einen gültigen Betrag eingeben.'];
        }

        // On-site, physical is the default (the customer can take the card now).
        $type = (($input['type'] ?? 'physical') === 'digital') ? 'digital' : 'physical';

        // A digital voucher has to be emailed, so an address is required.
        if ($type === 'digital') {
            $email = trim((string) ($input['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error' => 'Für digitale Gutscheine ist eine gültige E-Mail-Adresse erforderlich.'];
            }
        }

        $voucher = new Voucher;
        $voucher->number_source       = !empty($input['number']) ? 'manual' : 'auto';
        if (!empty($input['number'])) {
            $voucher->number = (int) $input['number'];
        }
        $voucher->type                = $type;
        $voucher->initial_value_cents = $valueCents;
        $voucher->recipient_name      = $input['recipient_name'] ?? null;
        $voucher->payment_status      = 'paid';
        $voucher->payment_method      = $input['payment_method'] ?? 'cash';
        $voucher->created_by          = $userId;

        try {
            $voucher->save();
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Konnte nicht gespeichert werden: ' . $e->getMessage()];
        }

        return ['success' => true, 'voucher' => $voucher];
    }

    /** "12,50" / "12.50" euro string -> integer cents. */
    public static function toCents($value): int
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        return (int) round(((float) $normalized) * 100);
    }

    public static function redeemError(string $code, $balanceCents): string
    {
        switch ($code) {
            case 'insufficient_balance':
                return 'Betrag übersteigt das Restguthaben (' . VoucherOrder::formatEuro((int) $balanceCents) . ').';
            case 'voucher_void':
                return 'Gutschein ist storniert.';
            case 'voucher_expired':
                return 'Gutschein ist abgelaufen.';
            case 'voucher_not_found':
                return 'Gutschein nicht gefunden.';
            default:
                return 'Einlösung fehlgeschlagen.';
        }
    }

    /** A full scan URL (...?t=<token>) or a bare token (<b64>.<b64>), else null. */
    protected static function extractToken(string $input): ?string
    {
        if (strpos($input, 't=') !== false) {
            $query = parse_url($input, PHP_URL_QUERY);
            parse_str($query !== null && $query !== false ? $query : $input, $params);
            if (!empty($params['t'])) {
                return $params['t'];
            }
        }

        // A bare token has a dot, no spaces, and is not a "MAM-…" code.
        if (strpos($input, '.') !== false
            && strpos($input, ' ') === false
            && !preg_match('/^' . VoucherCode::PREFIX . '-/i', $input)) {
            return $input;
        }

        return null;
    }
}
