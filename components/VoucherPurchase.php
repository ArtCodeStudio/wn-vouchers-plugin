<?php namespace JumpLink\Vouchers\Components;

use Log;
use Response;
use Redirect;
use Request;
use Illuminate\Support\Facades\RateLimiter;
use Cms\Classes\ComponentBase;
use JumpLink\Vouchers\Models\Settings;
use JumpLink\Vouchers\Classes\PurchaseService;
use JumpLink\Vouchers\Classes\PaymentService;
use JumpLink\Vouchers\Classes\NotificationService;

/**
 * VoucherPurchase – the customer-facing buy form that replaces the static
 * `jumplink-contact` snippet on the gutschein-kaufen page.
 *
 * onPurchase validates + creates the pending order, starts the Mollie payment
 * and redirects the buyer to the checkout. The Mollie webhook (not this handler)
 * is what later issues the voucher, so a closed browser tab can never leave a
 * paid order without a voucher.
 */
class VoucherPurchase extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => trans('jumplink.vouchers::lang.component.purchase_name'),
            'description' => trans('jumplink.vouchers::lang.component.purchase_description'),
        ];
    }

    public function minValueCents()
    {
        return (int) Settings::get('min_value_cents', 1000);
    }

    public function maxValueCents()
    {
        return (int) Settings::get('max_value_cents', 50000);
    }

    public function serviceFeeCents()
    {
        return (int) Settings::get('service_fee_cents', 250);
    }

    /** Quick-pick amounts with optional labels, e.g. [['cents'=>2100,'label'=>'…'], …]. */
    public function denominations()
    {
        return Settings::denominationList();
    }

    /** Max length of the personal message — drives the textarea's maxlength + hint. */
    public function messageMaxLength()
    {
        return PurchaseService::MESSAGE_MAX_LENGTH;
    }

    /** Payment methods offered on the form ('mollie' / 'banktransfer'), gated by config. */
    public function paymentMethods()
    {
        return PaymentService::availableMethods();
    }

    /** Show a method chooser only when more than one method is offered. */
    public function showPaymentChooser()
    {
        return count($this->paymentMethods()) > 1;
    }

    public function onPurchase()
    {
        // Rate-limit per IP: each accepted purchase starts a real Mollie payment,
        // so cap floods before any order row or payment is created. 20/min/IP
        // blocks automated floods while tolerating legitimate bursts (incl. a
        // shared office/NAT during the Christmas rush).
        $key = 'voucher-purchase:' . Request::ip();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            // Proper 429 (not a 500) so monitoring stays clean; the AJAX
            // framework shows the message to the buyer.
            return Response::make(trans('jumplink.vouchers::lang.error.too_many_requests'), 429);
        }
        RateLimiter::hit($key, 60);

        $input = post();
        $input['ip'] = Request::ip();

        $result = PurchaseService::createPendingOrder($input);

        // Honeypot tripped: look successful, do nothing.
        if (!empty($result['spam'])) {
            return ['#voucherPurchaseResult' => '<p class="voucher-ok">' . e(trans('jumplink.vouchers::lang.purchase.thank_you')) . '</p>'];
        }

        if (empty($result['success'])) {
            throw new \ValidationException($result['errors'] ?? ['face_value' => trans('jumplink.vouchers::lang.error.check_input')]);
        }

        $order = $result['order'];
        // The return page authorizes by the unguessable token, not the id.
        $returnUrl = url()->current() . '?order=' . $order->id . '&t=' . $order->access_token;

        // Bank transfer (Vorkasse): no online payment. Email the buyer the bank
        // details + reference and land them on the return page (which shows the
        // same instructions). The voucher is issued later, when staff confirm the
        // incoming payment in the backend.
        if ($order->isBankTransfer()) {
            NotificationService::sendBankTransferMails($order);
            return Redirect::to($returnUrl);
        }

        // Online payment via Mollie.
        if (!PaymentService::isConfigured()) {
            throw new \ApplicationException(trans('jumplink.vouchers::lang.error.payment_unavailable'));
        }
        try {
            $checkoutUrl = PaymentService::startPayment($order, $returnUrl);
        } catch (\Throwable $e) {
            Log::error('[vouchers] startPayment failed: ' . $e->getMessage());
            throw new \ApplicationException(trans('jumplink.vouchers::lang.error.payment_start_failed'));
        }

        return Redirect::to($checkoutUrl);
    }
}
