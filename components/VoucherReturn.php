<?php namespace JumpLink\Vouchers\Components;

use URL;
use Cms\Classes\ComponentBase;
use JumpLink\Vouchers\Models\VoucherOrder;

/**
 * VoucherReturn – the post-payment landing component. Reads ?order=<id> and
 * shows the status. While the webhook is still issuing the voucher it shows a
 * "payment processing" notice and polls; once issued it shows the PDF download
 * link ("also sent by email"). It NEVER issues a voucher itself — the Mollie
 * webhook is the only issuing authority.
 */
class VoucherReturn extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Gutschein-Rückkehr (nach Zahlung)',
            'description' => 'Landeseite nach der Mollie-Zahlung: Status + PDF-Download.',
        ];
    }

    public function orderId()
    {
        return (int) (input('order') ?: post('order'));
    }

    /**
     * The order referenced by ?order=<id>&t=<token>, if the token matches.
     * The token (not the enumerable id) is what authorizes access — see
     * VoucherOrder::findForReturn.
     */
    public function order()
    {
        return VoucherOrder::findForReturn($this->orderId(), input('t') ?: post('t'));
    }

    public function isIssued()
    {
        $order = $this->order();
        return $order && $order->status === 'issued';
    }

    /** Signed, time-limited PDF download URL once the voucher is issued. */
    public function downloadUrl()
    {
        $order = $this->order();
        if (!$order || $order->status !== 'issued') {
            return null;
        }
        $voucher = $order->vouchers()->first();
        if (!$voucher || $voucher->type !== 'digital') {
            return null;
        }
        return URL::temporarySignedRoute('jumplink.vouchers.pdf', now()->addDays(30), ['voucher' => $voucher->id]);
    }
}
