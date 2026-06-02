<?php namespace JumpLink\Vouchers\Components;

use Log;
use Redirect;
use Request;
use Cms\Classes\ComponentBase;
use JumpLink\Vouchers\Models\Settings;
use JumpLink\Vouchers\Classes\PurchaseService;
use JumpLink\Vouchers\Classes\PaymentService;

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
            'name'        => 'Gutschein-Kauf',
            'description' => 'Kaufformular für Gutscheine mit Mollie-Zahlung.',
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

    /** Quick-pick amounts in cents, e.g. [2500, 5000, 10000]. */
    public function denominations()
    {
        return collect(Settings::get('denominations', []))
            ->pluck('value_cents')
            ->filter()
            ->map(fn ($c) => (int) $c)
            ->values()
            ->all();
    }

    public function onPurchase()
    {
        $input = post();
        $input['ip'] = Request::ip();

        $result = PurchaseService::createPendingOrder($input);

        // Honeypot tripped: look successful, do nothing.
        if (!empty($result['spam'])) {
            return ['#voucherPurchaseResult' => '<p class="voucher-ok">Vielen Dank.</p>'];
        }

        if (empty($result['success'])) {
            throw new \ValidationException($result['errors'] ?? ['face_value' => 'Bitte prüfen Sie Ihre Eingaben.']);
        }

        if (!PaymentService::isConfigured()) {
            throw new \ApplicationException('Die Online-Zahlung ist derzeit nicht verfügbar. Bitte versuchen Sie es später erneut.');
        }

        $order = $result['order'];
        // The return page authorizes by the unguessable token, not the id.
        $returnUrl = url()->current() . '?order=' . $order->id . '&t=' . $order->access_token;

        try {
            $checkoutUrl = PaymentService::startPayment($order, $returnUrl);
        } catch (\Throwable $e) {
            Log::error('[vouchers] startPayment failed: ' . $e->getMessage());
            throw new \ApplicationException('Die Zahlung konnte nicht gestartet werden. Bitte versuchen Sie es erneut.');
        }

        return Redirect::to($checkoutUrl);
    }
}
