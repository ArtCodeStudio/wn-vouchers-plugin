<?php namespace JumpLink\Vouchers\Models;

use Model;

/**
 * Redemption – one immutable row of the append-only ledger. Positive
 * `amount_cents` = value taken off the voucher; negative = reversal/correction.
 *
 * VAT on a multi-purpose voucher is due at redemption, but that 7%/19% split is
 * recorded by the restaurant's (TSE) cash register when the meal is rung up —
 * the voucher is only the tender. This ledger therefore tracks the running
 * balance, not the VAT.
 */
class Redemption extends Model
{
    public $table = 'jumplink_vouchers_redemptions';

    // Ledger rows are immutable: created_at only, no updated_at.
    public $timestamps = false;

    public $fillable = [
        'voucher_id', 'amount_cents', 'balance_after_cents', 'kind',
        'note', 'redeemed_by', 'source', 'idempotency_key',
        'created_at',
    ];

    protected $dates = ['created_at'];

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
