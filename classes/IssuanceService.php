<?php namespace JumpLink\Vouchers\Classes;

use Db;
use Carbon\Carbon;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\VoucherOrder;

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

            // Idempotency keyed on "a voucher already exists for this order", not
            // on the status flag — so resetting an issued order's status back to
            // pending in the backend cannot mint a second voucher for one payment.
            // A unique index on vouchers.order_id is the hard DB-level backstop.
            $existing = $order->vouchers()->first();
            if ($existing) {
                // A voucher exists, so the order IS issued; self-heal a status
                // that was edited away from 'issued'.
                if ($order->status !== 'issued') {
                    $order->status = 'issued';
                    $order->save();
                }
                return ['voucher' => $existing, 'created' => false];
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
            $voucher->payment_status      = 'paid';   // confirmed paid before issuance
            // Online (Mollie) vs. bank transfer confirmed by staff — both are paid
            // by the time a voucher is issued.
            $voucher->payment_method      = $order->isBankTransfer() ? 'banktransfer' : 'online';
            $voucher->token_secret        = bin2hex(random_bytes(16));
            $voucher->recipient_name      = $order->recipient_name;
            $voucher->issued_at           = Carbon::now();
            // valid_until is applied centrally in Voucher::beforeCreate.
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
}
