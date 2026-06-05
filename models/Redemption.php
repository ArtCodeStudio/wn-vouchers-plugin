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
        return [
            'redeem'   => trans('jumplink.vouchers::lang.redemption_kind.redeem'),
            'reversal' => trans('jumplink.vouchers::lang.redemption_kind.reversal'),
            'adjust'   => trans('jumplink.vouchers::lang.redemption_kind.adjust'),
        ];
    }

    public function getKindLabelAttribute()
    {
        $options = $this->getKindOptions();
        return $options[$this->kind] ?? $this->kind;
    }

    public function getSourceOptions()
    {
        return [
            'pos'     => trans('jumplink.vouchers::lang.redemption_source.pos'),
            'backend' => trans('jumplink.vouchers::lang.redemption_source.backend'),
            'api'     => trans('jumplink.vouchers::lang.redemption_source.api'),
        ];
    }

    public function getSourceLabelAttribute()
    {
        $options = $this->getSourceOptions();
        return $options[$this->source] ?? $this->source;
    }

    public function getAmountEuroAttribute()
    {
        return VoucherOrder::formatEuro($this->amount_cents);
    }
}
