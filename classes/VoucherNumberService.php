<?php namespace JumpLink\Vouchers\Classes;

use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\Settings;

/**
 * Atomic, concurrency-safe allocation of the next automatic voucher number.
 *
 * MUST be called inside the same DB transaction that inserts the voucher, so
 * the number is committed atomically with the row. The `lockForUpdate()` on the
 * auto-number range serializes concurrent purchases (Christmas rush). Auto
 * numbers start at the configurable floor and stay above the low, hand-written
 * binder range (number_source = manual), so the two can never collide.
 */
class VoucherNumberService
{
    public static function allocate(): int
    {
        $start = (int) Settings::get('voucher_start_number', 100000);

        $max = Voucher::where('number_source', 'auto')
            ->lockForUpdate()
            ->max('number');

        // The start number is a floor, not just a one-off seed for the very first
        // voucher: the next number is the higher of (highest issued auto number + 1)
        // and the configured start. So an operator can raise the start to jump the
        // sequence forward to a new range at any time, while it can never move
        // backward onto an already-issued number (which would collide).
        $next = $max !== null ? ((int) $max + 1) : $start;
        return max($start, $next);
    }
}
