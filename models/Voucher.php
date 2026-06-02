<?php namespace JumpLink\Vouchers\Models;

use Model;
use Carbon\Carbon;

/**
 * Voucher – a gift voucher with an initial value and a running balance. The
 * balance is the cached result of the append-only redemption ledger; never
 * trust `balance_cents` for a redemption decision (see RedemptionService).
 */
class Voucher extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \Winter\Storm\Database\Traits\Purgeable;

    public $table = 'jumplink_vouchers_vouchers';

    public $rules = [
        'initial_value_cents' => 'required|integer|min:0',
        'number_source'       => 'required|in:auto,manual',
        'type'                => 'required|in:digital,physical',
        // The code is derived from the number, so only the number is required —
        // and only for manual (binder) vouchers; auto numbers are allocated.
        'number'              => 'required_if:number_source,manual',
    ];

    /** Backend-only form field (euro value); never persisted as a column. */
    protected $purgeable = ['value_euro'];

    public $fillable = [
        'order_id', 'code', 'number', 'number_source', 'type',
        'initial_value_cents', 'value_euro', 'balance_cents', 'currency', 'vat_mode',
        'status', 'payment_status', 'payment_method', 'token_secret',
        'recipient_name', 'valid_until', 'issued_at', 'pdf_generated_at', 'created_by',
    ];

    protected $dates = ['valid_until', 'issued_at', 'pdf_generated_at'];

    public $belongsTo = [
        'order' => [\JumpLink\Vouchers\Models\VoucherOrder::class, 'key' => 'order_id'],
    ];

    public $hasMany = [
        'redemptions' => [\JumpLink\Vouchers\Models\Redemption::class, 'key' => 'voucher_id'],
    ];

    public function getStatusOptions()
    {
        return [
            'active'   => 'Aktiv',
            'redeemed' => 'Eingelöst (0 €)',
            'void'     => 'Storniert',
            'expired'  => 'Abgelaufen',
        ];
    }

    public function getTypeOptions()
    {
        return ['digital' => 'Digital (PDF/QR)', 'physical' => 'Physisch (Karte)'];
    }

    public function getPaymentStatusOptions()
    {
        return ['paid' => 'Bezahlt', 'unpaid' => 'Offen / unbezahlt'];
    }

    public function getPaymentMethodOptions()
    {
        // Bar / Karte already imply payment at the till, so there is no separate
        // "paid externally at the POS" option.
        return [
            'cash'    => 'Bar',
            'card'    => 'Karte / EC',
            'invoice' => 'Auf Rechnung',
            'online'  => 'Online (Mollie)',
            'other'   => 'Sonstiges',
        ];
    }

    public function getPaymentStatusLabelAttribute()
    {
        $options = $this->getPaymentStatusOptions();
        return $options[$this->payment_status] ?? $this->payment_status;
    }

    //
    // Creation conveniences (so staff never type a code by hand)
    //

    public function beforeValidate()
    {
        // Auto numbers are allocated; manual (binder) numbers are typed by staff.
        if ($this->number_source === 'auto' && empty($this->number)) {
            $this->number = \JumpLink\Vouchers\Classes\VoucherNumberService::allocate();
        }
        // The human-readable code is always derived from the number.
        if (empty($this->code) && !empty($this->number)) {
            $this->code = \JumpLink\Vouchers\Classes\VoucherCode::format((int) $this->number);
        }
        // Every voucher needs a secret for its signed QR token.
        if (empty($this->token_secret)) {
            $this->token_secret = bin2hex(random_bytes(16));
        }
    }

    public function beforeCreate()
    {
        if (empty($this->status)) {
            $this->status = 'active';
        }
        if (empty($this->payment_status)) {
            $this->payment_status = 'paid';
        }
        // A fresh voucher starts with its full value available.
        if ((int) $this->balance_cents === 0 && (int) $this->initial_value_cents > 0) {
            $this->balance_cents = (int) $this->initial_value_cents;
        }
        // Apply the configured default expiry when none was entered (applies to
        // both online-issued and manually-created vouchers).
        if (empty($this->valid_until)) {
            $this->valid_until = static::configuredValidUntil();
        }
    }

    /**
     * The default expiry for a new voucher: the configured number of years,
     * rounded up to year-end so it matches the German statutory limitation
     * (§§195/199 BGB — the 3-year period is counted from the end of the issue
     * year). Returns null when the shop configured 0 years (print no expiry).
     */
    public static function configuredValidUntil(): ?Carbon
    {
        $years = (int) Settings::get('default_validity_years', 3);
        return $years > 0
            ? Carbon::now()->addYears($years)->endOfYear()
            : null;
    }

    /** Distinct recipient names seen so far (for backend autocomplete). */
    public static function distinctRecipients(): array
    {
        $fromVouchers = static::whereNotNull('recipient_name')
            ->where('recipient_name', '!=', '')
            ->distinct()
            ->pluck('recipient_name');

        $fromOrders = VoucherOrder::whereNotNull('recipient_name')
            ->where('recipient_name', '!=', '')
            ->distinct()
            ->pluck('recipient_name');

        return $fromVouchers->merge($fromOrders)->unique()->sort()->values()->all();
    }

    //
    // Euro <-> cents bridge for the backend form (value typed as "50,00")
    //

    public function getValueEuroAttribute()
    {
        return number_format(((int) $this->initial_value_cents) / 100, 2, ',', '');
    }

    public function setValueEuroAttribute($value)
    {
        if ($value === null || $value === '') {
            return;
        }
        $normalized = str_replace(',', '.', trim((string) $value));
        $this->initial_value_cents = (int) round(((float) $normalized) * 100);
    }

    //
    // Balance ledger
    //

    /**
     * The balance the ledger implies (source of truth). Does not persist.
     */
    public function ledgerBalance(): int
    {
        $sum = (int) $this->redemptions()->sum('amount_cents');
        return max(0, (int) $this->initial_value_cents - $sum);
    }

    /**
     * Recalculate the cached balance + status from the ledger and persist.
     * Used by RedemptionService and the jumplink:vouchers-verify command.
     */
    public function recomputeBalance(): self
    {
        $this->balance_cents = $this->ledgerBalance();
        if (!in_array($this->status, ['void', 'expired'], true)) {
            $this->status = $this->balance_cents <= 0 ? 'redeemed' : 'active';
        }
        $this->save();
        return $this;
    }

    //
    // Money display accessors (cents -> "50,00 €")
    //
    public function getBalanceEuroAttribute()
    {
        return VoucherOrder::formatEuro($this->balance_cents);
    }

    public function getInitialValueEuroAttribute()
    {
        return VoucherOrder::formatEuro($this->initial_value_cents);
    }
}
