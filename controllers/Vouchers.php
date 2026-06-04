<?php namespace JumpLink\Vouchers\Controllers;

use Flash;
use BackendAuth;
use BackendMenu;
use Backend\Classes\Controller;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Classes\RedemptionService;

/**
 * Vouchers Backend Controller – manage issued vouchers, balances and status,
 * and book ledger-safe (partial) redemptions from the voucher detail page.
 */
class Vouchers extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['jumplink.vouchers.manage_vouchers'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('JumpLink.Vouchers', 'vouchers', 'vouchers');
    }

    /**
     * Book a (partial) redemption from the voucher form, then re-render the
     * redeem panel with the new balance + ledger. The redemption itself is
     * decided against the ledger inside a row lock (RedemptionService), so the
     * cached balance shown here can never cause an over-redemption.
     */
    public function onRedeem()
    {
        if (!BackendAuth::userHasAccess('jumplink.vouchers.redeem_vouchers')
            && !BackendAuth::userHasAccess('jumplink.vouchers.manage_vouchers')) {
            throw new \ApplicationException(trans('jumplink.vouchers::lang.redeem.no_permission'));
        }

        $id = (int) post('voucher_id');
        $amountCents = self::toCents(post('amount'));
        if ($amountCents <= 0) {
            throw new \ApplicationException(trans('jumplink.vouchers::lang.error.invalid_amount'));
        }

        $user = BackendAuth::getUser();
        $result = RedemptionService::redeem($id, $amountCents, [
            'source'      => 'backend',
            'redeemed_by' => $user ? $user->id : null,
            'note'        => post('note') ?: null,
        ]);

        if (empty($result['success'])) {
            throw new \ApplicationException(self::redeemError($result['error'] ?? 'error', $result['balance_cents'] ?? null));
        }

        Flash::success(trans('jumplink.vouchers::lang.flash.redeem_booked', ['balance' => VoucherOrder::formatEuro($result['balance_cents'])]));

        return ['#voucherRedeemPanel' => $this->makePartial('redeem', ['formModel' => Voucher::find($id)])];
    }

    /** "12,50" / "12.50" euro string -> integer cents. */
    protected static function toCents($value): int
    {
        $normalized = str_replace(',', '.', trim((string) $value));
        return (int) round(((float) $normalized) * 100);
    }

    protected static function redeemError(string $code, $balanceCents): string
    {
        switch ($code) {
            case 'insufficient_balance':
                return trans('jumplink.vouchers::lang.error.insufficient_balance', ['balance' => VoucherOrder::formatEuro((int) $balanceCents)]);
            case 'voucher_void':
                return trans('jumplink.vouchers::lang.error.voucher_void');
            case 'voucher_expired':
                return trans('jumplink.vouchers::lang.error.voucher_expired');
            case 'voucher_not_found':
                return trans('jumplink.vouchers::lang.error.voucher_not_found_short');
            default:
                return trans('jumplink.vouchers::lang.error.redeem_failed');
        }
    }
}
