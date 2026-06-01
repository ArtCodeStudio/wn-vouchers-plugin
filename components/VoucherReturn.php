<?php namespace JumpLink\Vouchers\Components;

use Cms\Classes\ComponentBase;

/**
 * VoucherReturn – the post-payment landing component. Reads ?order=<id>, polls
 * the order status, and once the webhook has issued the voucher shows the PDF
 * download link ("also sent by email"). It NEVER issues a voucher itself — the
 * Mollie webhook is the only issuing authority. Implemented in M1.
 */
class VoucherReturn extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Gutschein-Rückkehr (nach Zahlung)',
            'description' => 'Landeseite nach der Mollie-Zahlung: Status-Poll + PDF-Download. M1.',
        ];
    }
}
