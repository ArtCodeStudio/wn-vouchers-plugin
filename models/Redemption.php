<?php namespace JumpLink\Vouchers\Models;

use Model;

/**
 * Redemption – one immutable row of the append-only ledger. Positive
 * `amount_cents` = value taken off the voucher; negative = reversal/correction.
 * `vat_breakdown` records the 7%/19% split chosen at the till (multi-purpose
 * voucher → VAT due on redemption).
 */
class Redemption extends Model
{
    public $table = 'jumplink_vouchers_redemptions';

    // Ledger rows are immutable: created_at only, no updated_at.
    public $timestamps = false;

    public $fillable = [
        'voucher_id', 'amount_cents', 'balance_after_cents', 'kind',
        'vat_breakdown', 'note', 'redeemed_by', 'source', 'idempotency_key',
        'created_at',
    ];

    protected $dates = ['created_at'];

    protected $jsonable = ['vat_breakdown'];

    public $belongsTo = [
        'voucher' => [\JumpLink\Vouchers\Models\Voucher::class, 'key' => 'voucher_id'],
    ];

    public function getKindOptions()
    {
        return ['redeem' => 'Einlösung', 'reversal' => 'Storno', 'adjust' => 'Korrektur'];
    }

    public function getAmountEuroAttribute()
    {
        return VoucherOrder::formatEuro($this->amount_cents);
    }
}
