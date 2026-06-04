<?php namespace JumpLink\Vouchers\Models;

use Model;
use Carbon\Carbon;

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
        'accounting_ref', 'ip', 'shipped_at', 'shipped_by',
    ];

    protected $dates = ['paid_at', 'accounting_synced_at', 'shipped_at'];

    /**
     * Mint the per-order access token. The return page is reached with an
     * enumerable id, so it must additionally present this token (see
     * findForReturn) before exposing order state or a signed PDF link.
     */
    public function beforeCreate()
    {
        if (empty($this->access_token)) {
            $this->access_token = bin2hex(random_bytes(16));
        }
    }

    /**
     * Look up an order for the post-payment return page: the integer id alone is
     * not enough — the request must also present the matching access token
     * (constant-time compared), otherwise order ids could be enumerated to reach
     * other buyers' vouchers (IDOR).
     */
    public static function findForReturn($id, ?string $token): ?self
    {
        $id = (int) $id;
        $token = (string) $token;
        if ($id <= 0 || $token === '') {
            return null;
        }
        $order = static::find($id);
        if (!$order || !$order->access_token || !hash_equals($order->access_token, $token)) {
            return null;
        }
        return $order;
    }

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
        return ['digital' => 'Digital (Bild/QR)', 'physical' => 'Physisch (Post)'];
    }

    /**
     * Signed, time-limited download URL for this order's digital voucher image,
     * or null if it is not issued / has no digital voucher. Single source for the
     * return component and the status-poll endpoint.
     */
    public function digitalDownloadUrl(): ?string
    {
        if ($this->status !== 'issued') {
            return null;
        }
        $voucher = $this->vouchers()->first();
        if (!$voucher || $voucher->type !== 'digital') {
            return null;
        }
        return \URL::temporarySignedRoute('jumplink.vouchers.image', now()->addDays(30), ['voucher' => $voucher->id]);
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
     * not yet posted.
     */
    public static function openFulfillmentCount()
    {
        return (int) self::where('delivery_type', 'physical')
            ->whereIn('status', ['paid', 'issued'])
            ->whereNull('shipped_at')
            ->count();
    }

    /** A physical order that is paid/issued and not yet posted. */
    public function needsShipping(): bool
    {
        return $this->delivery_type === 'physical'
            && in_array($this->status, ['paid', 'issued'], true)
            && !$this->shipped_at;
    }

    /**
     * Mark a physical order as posted. Idempotent: returns false if it is not a
     * shippable physical order or was already shipped (so no duplicate mail).
     */
    public function markShipped(?int $userId = null): bool
    {
        if ($this->delivery_type !== 'physical' || $this->shipped_at) {
            return false;
        }
        $this->shipped_at = Carbon::now();
        $this->shipped_by = $userId;
        $this->save();
        return true;
    }
}
