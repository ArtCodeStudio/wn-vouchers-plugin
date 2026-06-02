<?php namespace JumpLink\Vouchers\Classes;

use Db;
use Carbon\Carbon;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;

/**
 * Turns a paid order into issued voucher(s).
 *
 * In the online flow the only caller is PaymentService::handleWebhook (the
 * Mollie webhook is the sole issuing authority). Issuance is idempotent: a
 * retried webhook for an already-issued order returns the existing voucher
 * without creating a second one. The order row is locked for the duration so
 * two concurrent webhooks cannot both issue.
 *
 * The voucher number is allocated inside this same transaction
 * (VoucherNumberService::allocate locks the auto-number range), so the number
 * is committed atomically with the voucher row.
 *
 * Result: ['voucher' => Voucher, 'created' => bool]
 */
class IssuanceService
{
    public static function issueForOrder(VoucherOrder $order): array
    {
        return Db::transaction(function () use ($order) {
            $order = VoucherOrder::lockForUpdate()->find($order->id);

            if ($order->status === 'issued') {
                return ['voucher' => $order->vouchers()->first(), 'created' => false];
            }

            $number = VoucherNumberService::allocate(); // locks the auto-number range

            $voucher = new Voucher;
            $voucher->order_id            = $order->id;
            $voucher->number              = $number;
            $voucher->number_source       = 'auto';
            $voucher->code                = VoucherCode::format($number);
            $voucher->type                = $order->delivery_type;
            $voucher->initial_value_cents = (int) $order->face_value_cents;
            $voucher->balance_cents       = (int) $order->face_value_cents;
            $voucher->currency            = $order->currency ?: 'EUR';
            $voucher->vat_mode            = $order->vat_mode;
            $voucher->status              = 'active';
            $voucher->token_secret        = bin2hex(random_bytes(16));
            $voucher->recipient_name      = $order->recipient_name;
            $voucher->valid_until         = self::defaultValidUntil();
            $voucher->issued_at           = Carbon::now();
            $voucher->save();

            $order->status         = 'issued';
            $order->payment_status = 'paid';
            if (!$order->paid_at) {
                $order->paid_at = Carbon::now();
            }
            $order->save();

            return ['voucher' => $voucher, 'created' => true];
        });
    }

    /** Default expiry: now + configured months (German gift-voucher norm ~3y). */
    protected static function defaultValidUntil(): Carbon
    {
        $months = (int) Settings::get('default_validity_months', 36);
        return Carbon::now()->addMonths(max(1, $months))->endOfDay();
    }
}
