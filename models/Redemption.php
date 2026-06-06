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

    /**
     * The VAT split formatted for the (read-only) backend form. `vat_breakdown`
     * is a jsonable array of rows ([{rate, net_cents, vat_cents, gross_cents}]);
     * binding that array straight to a textarea throws "Array to string
     * conversion", so the form binds to this string instead. Empty when no split
     * was recorded (e.g. multi-purpose vouchers before a rate was captured).
     */
    public function getVatBreakdownTextAttribute()
    {
        $rows = $this->vat_breakdown;
        if (empty($rows) || !is_array($rows)) {
            return '';
        }

        $lines = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = trans('jumplink.vouchers::lang.redeem.vat_breakdown_row', [
                'rate'  => $row['rate'] ?? '?',
                'gross' => VoucherOrder::formatEuro($row['gross_cents'] ?? 0),
                'net'   => VoucherOrder::formatEuro($row['net_cents'] ?? 0),
                'vat'   => VoucherOrder::formatEuro($row['vat_cents'] ?? 0),
            ]);
        }
        return implode("\n", $lines);
    }
}
