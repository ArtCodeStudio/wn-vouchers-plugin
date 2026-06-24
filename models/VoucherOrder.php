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

    protected $dates = ['paid_at', 'accounting_synced_at', 'shipped_at', 'anonymized_at'];

    /**
     * Buyer-controlled personal data, nulled by anonymize(). The fiscal fields
     * (amounts, vat, provider, payment_id, paid_at, status, accounting_ref) are
     * deliberately NOT listed — they must survive for the statutory retention.
     */
    const PERSONAL_FIELDS = [
        'firstname', 'lastname', 'email', 'phone',
        'street', 'zip', 'city', 'recipient_name', 'message', 'ip',
    ];

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
            'pending'   => trans('jumplink.vouchers::lang.order_status.pending'),
            'paid'      => trans('jumplink.vouchers::lang.order_status.paid'),
            'issued'    => trans('jumplink.vouchers::lang.order_status.issued'),
            'failed'    => trans('jumplink.vouchers::lang.order_status.failed'),
            'cancelled' => trans('jumplink.vouchers::lang.order_status.cancelled'),
            'expired'   => trans('jumplink.vouchers::lang.order_status.expired'),
            'refunded'  => trans('jumplink.vouchers::lang.order_status.refunded'),
        ];
    }

    public function getDeliveryTypeOptions()
    {
        return [
            'digital'  => trans('jumplink.vouchers::lang.delivery.digital'),
            'physical' => trans('jumplink.vouchers::lang.delivery.physical'),
        ];
    }

    /**
     * The order's payment status is the raw Mollie status (set by PaymentService),
     * so it has its own translation set — distinct from the voucher's paid/unpaid
     * till semantics. Note Mollie spells it "canceled" (one l).
     */
    public function getPaymentStatusOptions()
    {
        return [
            'open'       => trans('jumplink.vouchers::lang.order_payment_status.open'),
            'pending'    => trans('jumplink.vouchers::lang.order_payment_status.pending'),
            'authorized' => trans('jumplink.vouchers::lang.order_payment_status.authorized'),
            'paid'       => trans('jumplink.vouchers::lang.order_payment_status.paid'),
            'failed'     => trans('jumplink.vouchers::lang.order_payment_status.failed'),
            'canceled'   => trans('jumplink.vouchers::lang.order_payment_status.canceled'),
            'expired'    => trans('jumplink.vouchers::lang.order_payment_status.expired'),
        ];
    }

    public function getVatModeOptions()
    {
        return [
            'multi_purpose'  => trans('jumplink.vouchers::lang.vat_mode_option.multi_purpose'),
            'single_purpose' => trans('jumplink.vouchers::lang.vat_mode_option.single_purpose'),
        ];
    }

    //
    // Translated label accessors so list columns + read-only form fields show the
    // localised label instead of the raw stored code (Winter only auto-translates
    // form dropdowns, not type:text list columns).
    //
    public function getStatusLabelAttribute()
    {
        $options = $this->getStatusOptions();
        return $options[$this->status] ?? $this->status;
    }

    public function getDeliveryTypeLabelAttribute()
    {
        $options = $this->getDeliveryTypeOptions();
        return $options[$this->delivery_type] ?? $this->delivery_type;
    }

    public function getPaymentStatusLabelAttribute()
    {
        $options = $this->getPaymentStatusOptions();
        return $options[$this->payment_status] ?? $this->payment_status;
    }

    public function getVatModeLabelAttribute()
    {
        $options = $this->getVatModeOptions();
        return $options[$this->vat_mode] ?? $this->vat_mode;
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
        return \URL::temporarySignedRoute('jumplink.vouchers.image', now()->addDays(7), ['voucher' => $voucher->id]);
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

    /**
     * Counter for the backend menu: bank-transfer (Vorkasse) orders still
     * awaiting their incoming payment — the staff action is to watch for the
     * transfer and confirm it ("Zahlung bestätigt").
     */
    public static function awaitingPaymentCount()
    {
        return (int) self::where('provider', 'banktransfer')
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Total orders needing staff action, for the Orders menu badge: bank
     * transfers awaiting payment + physical cards awaiting posting. The two sets
     * are disjoint — an order awaiting payment is still `pending`, while one
     * awaiting shipping is already `paid`/`issued`.
     */
    public static function openActionCount()
    {
        return self::awaitingPaymentCount() + self::openFulfillmentCount();
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

    /** Was this order placed for payment by bank transfer (Vorkasse)? */
    public function isBankTransfer(): bool
    {
        return $this->provider === 'banktransfer';
    }

    /** A bank-transfer order still awaiting its incoming payment (no voucher yet). */
    public function awaitingTransfer(): bool
    {
        return $this->isBankTransfer() && $this->status !== 'issued';
    }

    /**
     * Stable per-order document number, used both as the bank-transfer reference
     * ("Verwendungszweck") and as the purchase-receipt (Beleg) number.
     */
    public function getReceiptNumberAttribute(): string
    {
        return 'GS-' . $this->id;
    }

    /** Human payment reference for the bank transfer ("Verwendungszweck"). */
    public function getTransferReferenceAttribute(): string
    {
        return $this->receipt_number;
    }

    /**
     * GDPR erasure (Art. 17 DSGVO): null the buyer's personal data while keeping
     * the fiscal record (amounts, vat, payment id, paid date, status) for the
     * statutory retention period (§ 147 AO / § 257 HGB; Art. 17 Abs. 3 lit. b
     * DSGVO). Idempotent — returns false if already anonymised. Uses forceSave()
     * so the "email/firstname required" rules cannot block the erasure.
     */
    public function anonymize(): bool
    {
        if ($this->anonymized_at) {
            return false;
        }
        foreach (self::PERSONAL_FIELDS as $field) {
            $this->$field = null;
        }
        $this->anonymized_at = Carbon::now();
        $this->forceSave();
        return true;
    }

    /** Has this order's personal data already been anonymised? */
    public function isAnonymized(): bool
    {
        return (bool) $this->anonymized_at;
    }
}
