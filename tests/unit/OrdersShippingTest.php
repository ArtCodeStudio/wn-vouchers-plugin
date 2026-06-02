<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\VoucherOrder;

/**
 * Physical fulfillment: marking an order as posted stamps the shipping date,
 * removes it from the open-fulfillment counter, and is idempotent. Digital
 * orders are never shippable.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class OrdersShippingTest extends PluginTestCase
{
    protected function makePhysicalOrder(string $status = 'issued'): VoucherOrder
    {
        return VoucherOrder::create([
            'delivery_type'     => 'physical',
            'face_value_cents'  => 5000,
            'service_fee_cents' => 250,
            'total_cents'       => 5250,
            'currency'          => 'EUR',
            'vat_mode'          => 'multi_purpose',
            'status'            => $status,
            'firstname'         => 'Test',
            'email'             => 'buyer@example.test',
            'street'            => 'Deichstr. 1',
            'zip'               => '27476',
            'city'              => 'Cuxhaven',
        ]);
    }

    public function testMarkShippedStampsAndExcludesFromCounter()
    {
        $order = $this->makePhysicalOrder('issued');
        $this->assertSame(1, VoucherOrder::openFulfillmentCount());
        $this->assertTrue($order->needsShipping());

        $this->assertTrue($order->markShipped(7));

        $order = $order->fresh();
        $this->assertNotNull($order->shipped_at);
        $this->assertSame(7, (int) $order->shipped_by);
        $this->assertFalse($order->needsShipping());
        $this->assertSame(0, VoucherOrder::openFulfillmentCount());
    }

    public function testMarkShippedIsIdempotent()
    {
        $order = $this->makePhysicalOrder('issued');

        $this->assertTrue($order->markShipped());
        $this->assertFalse($order->markShipped()); // already posted — no duplicate
    }

    public function testDigitalOrderIsNotShippable()
    {
        $order = VoucherOrder::create([
            'delivery_type'    => 'digital',
            'face_value_cents' => 5000,
            'total_cents'      => 5000,
            'currency'         => 'EUR',
            'vat_mode'         => 'multi_purpose',
            'status'           => 'issued',
            'firstname'        => 'Test',
            'email'            => 'buyer@example.test',
        ]);

        $this->assertFalse($order->markShipped());
        $this->assertFalse($order->needsShipping());
        $this->assertSame(0, VoucherOrder::openFulfillmentCount());
    }
}
