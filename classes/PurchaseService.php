<?php namespace JumpLink\Vouchers\Classes;

use Validator;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;

/**
 * Creates the pending order from a purchase request (honeypot + validation +
 * fee/total computation). Mirrors the JumpLink.Events BookingService pattern.
 * The Mollie payment is started separately by PaymentService (M1); the webhook
 * is the only authority that later issues the voucher.
 *
 * Result: ['success'=>bool, 'status'=>int, 'order'?=>VoucherOrder,
 *          'errors'?=>array, 'spam'?=>bool]
 */
class PurchaseService
{
    public static function createPendingOrder(array $input): array
    {
        // Honeypot: bots fill 'website' -> look successful, ignore.
        if (!empty($input['website'])) {
            return ['success' => true, 'status' => 200, 'order' => null, 'spam' => true];
        }

        $delivery = (($input['delivery_type'] ?? 'digital') === 'physical') ? 'physical' : 'digital';

        $rules = [
            'firstname' => 'required|min:2',
            'email'     => 'required|email',
        ];
        if ($delivery === 'physical') {
            $rules += ['street' => 'required', 'zip' => 'required', 'city' => 'required'];
        }

        $validator = Validator::make($input, $rules, [], self::attributeNames());
        if ($validator->fails()) {
            return ['success' => false, 'status' => 422, 'errors' => $validator->errors()->toArray()];
        }

        $faceCents = self::toCents($input['face_value'] ?? ($input['face_value_cents'] ?? 0));
        $min = (int) Settings::get('min_value_cents', 1000);
        $max = (int) Settings::get('max_value_cents', 50000);
        if ($faceCents < $min || $faceCents > $max) {
            return [
                'success' => false,
                'status'  => 422,
                'errors'  => ['face_value' => [trans('jumplink.vouchers::lang.error.amount_out_of_range')]],
            ];
        }

        $feeCents = $delivery === 'physical' ? (int) Settings::get('service_fee_cents', 250) : 0;
        $vatMode  = (string) Settings::get('vat_mode', 'multi_purpose');

        $order = new VoucherOrder;
        $order->delivery_type     = $delivery;
        $order->face_value_cents  = $faceCents;
        $order->service_fee_cents = $feeCents;
        $order->total_cents       = $faceCents + $feeCents;
        $order->vat_mode          = $vatMode;
        $order->vat_rate          = $vatMode === 'single_purpose' ? (float) Settings::get('vat_rate', 19) : null;
        $order->status            = 'pending';
        $order->firstname         = $input['firstname'] ?? '';
        $order->lastname          = $input['lastname'] ?? null;
        $order->email             = $input['email'];
        $order->phone             = $input['phone'] ?? null;
        $order->street            = $input['street'] ?? null;
        $order->zip               = $input['zip'] ?? null;
        $order->city              = $input['city'] ?? null;
        $order->recipient_name    = $input['recipient_name'] ?? null;
        $order->message           = $input['message'] ?? null;
        $order->ip                = $input['ip'] ?? null;
        $order->save();

        return ['success' => true, 'status' => 200, 'order' => $order];
    }

    /**
     * Localised display names for the validated fields, so a failed rule reads
     * "E-Mail wird benötigt." rather than leaking the raw key ("email …"). Falls
     * back to the field key for anything not listed.
     */
    protected static function attributeNames(): array
    {
        $names = [];
        foreach (['firstname', 'lastname', 'email', 'phone', 'street', 'zip', 'city'] as $field) {
            $names[$field] = trans("jumplink.vouchers::lang.field.$field");
        }
        return $names;
    }

    /** Accepts an int (already cents) or a "12,50"/"12.50" euro string. */
    protected static function toCents($value): int
    {
        if (is_int($value)) {
            return $value;
        }
        $normalized = str_replace(',', '.', trim((string) $value));
        return (int) round(((float) $normalized) * 100);
    }
}
