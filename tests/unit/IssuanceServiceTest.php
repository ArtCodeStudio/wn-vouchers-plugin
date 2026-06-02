<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;
use JumpLink\Vouchers\Classes\IssuanceService;
use JumpLink\Vouchers\Classes\VoucherCode;

/**
 * Issuance: turning a paid order into a voucher. Numbering atomicity, the
 * digital/physical type, and idempotency on webhook retries.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class IssuanceServiceTest extends PluginTestCase
{
    protected function makeOrder(string $delivery = 'digital', int $face = 5000): VoucherOrder
    {
        $attrs = [
            'delivery_type'     => $delivery,
            'face_value_cents'  => $face,
            'service_fee_cents' => $delivery === 'physical' ? 250 : 0,
            'total_cents'       => $face + ($delivery === 'physical' ? 250 : 0),
            'currency'          => 'EUR',
            'vat_mode'          => 'multi_purpose',
            'status'            => 'pending',
            'firstname'         => 'Test',
            'lastname'          => 'Buyer',
            'email'             => 'buyer@example.test',
        ];
        if ($delivery === 'physical') {
            $attrs += ['street' => 'Deichstr. 1', 'zip' => '27476', 'city' => 'Cuxhaven'];
        }
        return VoucherOrder::create($attrs);
    }

    public function testIssuesActiveVoucherForPaidOrder()
    {
        $order = $this->makeOrder('digital', 5000);

        $result = IssuanceService::issueForOrder($order);

        $this->assertTrue($result['created']);
        $voucher = $result['voucher'];
        $this->assertSame('active', $voucher->status);
        $this->assertSame('digital', $voucher->type);
        $this->assertSame('auto', $voucher->number_source);
        $this->assertSame(5000, (int) $voucher->initial_value_cents);
        $this->assertSame(5000, (int) $voucher->balance_cents);
        $this->assertTrue(VoucherCode::isValid($voucher->code));
        $this->assertNotEmpty($voucher->token_secret);

        $order = $order->fresh();
        $this->assertSame('issued', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
    }

    public function testIssuanceIsIdempotent()
    {
        $order = $this->makeOrder('digital', 5000);

        $first  = IssuanceService::issueForOrder($order);
        $second = IssuanceService::issueForOrder($order->fresh());

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($first['voucher']->id, $second['voucher']->id);
        $this->assertSame(1, $order->fresh()->vouchers()->count());
    }

    public function testPhysicalOrderIssuesPhysicalVoucher()
    {
        $order = $this->makeOrder('physical', 5000);

        $voucher = IssuanceService::issueForOrder($order)['voucher'];

        $this->assertSame('physical', $voucher->type);
        $this->assertSame(5000, (int) $voucher->initial_value_cents); // fee is not part of voucher value
    }

    public function testNumbersAreSequentialAndStartAtConfiguredFloor()
    {
        Settings::set('voucher_start_number', 500000);

        $a = IssuanceService::issueForOrder($this->makeOrder())['voucher'];
        $b = IssuanceService::issueForOrder($this->makeOrder())['voucher'];

        $this->assertSame(500000, (int) $a->number);
        $this->assertSame(500001, (int) $b->number);
    }
}
