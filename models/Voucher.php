<?php namespace JumpLink\Vouchers\Models;

use Model;

/**
 * Voucher – a gift voucher with an initial value and a running balance. The
 * balance is the cached result of the append-only redemption ledger; never
 * trust `balance_cents` for a redemption decision (see RedemptionService).
 */
class Voucher extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    public $table = 'jumplink_vouchers_vouchers';

    public $rules = [
        'code'                => 'required',
        'initial_value_cents' => 'required|integer|min:0',
        'number_source'       => 'required|in:auto,manual',
        'type'                => 'required|in:digital,physical',
    ];

    public $fillable = [
        'order_id', 'code', 'number', 'number_source', 'type',
        'initial_value_cents', 'balance_cents', 'currency', 'vat_mode',
        'status', 'token_secret', 'recipient_name', 'valid_until',
        'issued_at', 'pdf_generated_at', 'created_by',
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
