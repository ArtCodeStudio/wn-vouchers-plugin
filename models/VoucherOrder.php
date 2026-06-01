<?php namespace JumpLink\Vouchers\Models;

use Model;

/**
 * VoucherOrder – the purchase/payment record. Created `pending`, becomes
 * `issued` once the Mollie webhook confirms payment and the voucher(s) are
 * created. Money is stored in integer cents.
 */
class VoucherOrder extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    public $table = 'jumplink_vouchers_voucher_orders';

    public $rules = [
        'delivery_type' => 'required|in:digital,physical',
        'email'         => 'required|email',
        'firstname'     => 'required|min:2',
        // Address is required only for physical delivery.
        'street'        => 'required_if:delivery_type,physical',
        'zip'           => 'required_if:delivery_type,physical',
        'city'          => 'required_if:delivery_type,physical',
    ];

    public $fillable = [
        'delivery_type', 'face_value_cents', 'service_fee_cents', 'total_cents',
        'currency', 'vat_mode', 'vat_rate', 'status',
        'firstname', 'lastname', 'email', 'phone', 'street', 'zip', 'city', 'country',
        'recipient_name', 'message',
        'provider', 'payment_id', 'payment_status', 'paid_at',
        'accounting_ref', 'ip',
    ];

    protected $dates = ['paid_at', 'accounting_synced_at'];

    public $hasMany = [
        'vouchers' => [\JumpLink\Vouchers\Models\Voucher::class, 'key' => 'order_id'],
    ];

    public function getStatusOptions()
    {
        return [
            'pending'   => 'Offen (Zahlung ausstehend)',
            'paid'      => 'Bezahlt',
            'issued'    => 'Ausgestellt',
            'failed'    => 'Fehlgeschlagen',
            'cancelled' => 'Abgebrochen',
            'expired'   => 'Abgelaufen',
            'refunded'  => 'Erstattet',
        ];
    }

    public function getDeliveryTypeOptions()
    {
        return ['digital' => 'Digital (PDF/QR)', 'physical' => 'Physisch (Post)'];
    }

    //
    // Money display accessors (cents -> "50,00 €")
    //
    public function getFaceValueEuroAttribute()
    {
        return self::formatEuro($this->face_value_cents);
    }

    public function getTotalEuroAttribute()
    {
        return self::formatEuro($this->total_cents);
    }

    public static function formatEuro($cents)
    {
        return number_format(((int) $cents) / 100, 2, ',', '.') . ' €';
    }

    /**
     * Counter for the backend menu: physical orders that are paid/issued and
     * still need to be posted. (Shipping status is modelled in M2.)
     */
    public static function openFulfillmentCount()
    {
        return (int) self::where('delivery_type', 'physical')
            ->whereIn('status', ['paid', 'issued'])
            ->count();
    }
}
