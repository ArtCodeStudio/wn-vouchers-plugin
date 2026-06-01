<?php namespace JumpLink\Vouchers\Components;

use Cms\Classes\ComponentBase;
use JumpLink\Vouchers\Models\Settings;

/**
 * VoucherPurchase – the customer-facing buy form that replaces the static
 * `jumplink-contact` snippet on the gutschein-kaufen page.
 *
 * M1: server-rendered form + AJAX onPurchase handler (mirroring
 * EventList::onBook) that calls PurchaseService + PaymentService and redirects
 * to the Mollie checkout. For now it exposes the configured amount bounds and
 * denominations so the markup can be built and reviewed.
 */
class VoucherPurchase extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Gutschein-Kauf',
            'description' => 'Kaufformular für Gutscheine (Mollie-Zahlung). Implementierung in M1.',
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

    public function denominations()
    {
        return Settings::get('denominations', []);
    }

    // public function onPurchase() { /* M1: PurchaseService + PaymentService */ }
}
