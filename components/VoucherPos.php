<?php namespace JumpLink\Vouchers\Components;

use Cms\Classes\ComponentBase;
use BackendAuth;

/**
 * VoucherPos – the staff till page (iPad). Gated by a backend session + the
 * jumplink.vouchers.redeem_vouchers permission. Looks up a voucher by code or
 * QR scan, shows the balance, and records a (partial) redemption via
 * RedemptionService (with the VAT split). Implemented in M1/M3.
 */
class VoucherPos extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Gutschein-Kasse (Einlösung)',
            'description' => 'Tablet-Einlöseseite für das Personal (Lookup, Restguthaben, Teileinlösung). M1/M3.',
        ];
    }

    /**
     * Whether the current visitor may use the till page.
     */
    public function authorized(): bool
    {
        $user = BackendAuth::getUser();
        return $user && $user->hasAccess('jumplink.vouchers.redeem_vouchers');
    }
}
