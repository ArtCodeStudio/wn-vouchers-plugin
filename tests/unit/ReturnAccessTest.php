<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\VoucherOrder;

/**
 * The post-payment return page is reached with an enumerable order id, so access
 * must be gated by the per-order token, not the id (IDOR). These tests pin that
 * behaviour: a token is minted on create, and findForReturn only resolves an
 * order when the matching token is presented.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class ReturnAccessTest extends PluginTestCase
{
    protected function makeOrder(): VoucherOrder
    {
        return VoucherOrder::create([
            'delivery_type'    => 'digital',
            'face_value_cents' => 5000,
            'total_cents'      => 5000,
            'currency'         => 'EUR',
            'vat_mode'         => 'multi_purpose',
            'status'           => 'issued',
            'firstname'        => 'Test',
            'email'            => 'buyer@example.test',
        ]);
    }

    public function testOrderGetsAccessTokenOnCreate()
    {
        $order = $this->makeOrder();

        $this->assertNotEmpty($order->access_token);
        $this->assertSame(32, strlen($order->access_token)); // 16 random bytes, hex
    }

    public function testFindForReturnRequiresMatchingToken()
    {
        $order = $this->makeOrder();

        // Correct token resolves the order.
        $this->assertNotNull(VoucherOrder::findForReturn($order->id, $order->access_token));

        // Wrong / empty / missing token is rejected.
        $this->assertNull(VoucherOrder::findForReturn($order->id, 'wrong-token'));
        $this->assertNull(VoucherOrder::findForReturn($order->id, ''));
        $this->assertNull(VoucherOrder::findForReturn($order->id, null));

        // Enumerating another id without its own token is rejected.
        $other = $this->makeOrder();
        $this->assertNull(VoucherOrder::findForReturn($other->id, $order->access_token));
    }
}
