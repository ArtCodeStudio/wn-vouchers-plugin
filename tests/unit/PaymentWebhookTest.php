<?php namespace JumpLink\Vouchers\Tests\Unit;

use Mail;
use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Classes\PaymentService;

/**
 * The Mollie webhook is the sole voucher-issuing authority. These tests inject a
 * fake Mollie client at the PaymentService seam, so no network is touched: a
 * `paid` payment issues exactly one voucher and is idempotent on retries, while
 * non-paid states issue nothing.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class PaymentWebhookTest extends PluginTestCase
{
    public function tearDown(): void
    {
        PaymentService::setClientResolver(null);
        parent::tearDown();
    }

    /** A fake Mollie client whose payments->get() returns a payment in $status. */
    protected function fakeClient(string $status, $orderId, string $paymentId)
    {
        $payment = new class($status, $orderId, $paymentId) {
            public $id;
            public $status;
            public $metadata;
            public function __construct($status, $orderId, $paymentId)
            {
                $this->status = $status;
                $this->id = $paymentId;
                $this->metadata = (object) ['order_id' => $orderId];
            }
            public function isPaid(): bool { return $this->status === 'paid'; }
            public function isCanceled(): bool { return $this->status === 'canceled'; }
            public function isExpired(): bool { return $this->status === 'expired'; }
            public function isFailed(): bool { return $this->status === 'failed'; }
        };

        return new class($payment) {
            public $payments;
            public function __construct($payment)
            {
                $this->payments = new class($payment) {
                    private $p;
                    public function __construct($p) { $this->p = $p; }
                    public function get($id, $query = [], $testmode = false) { return $this->p; }
                };
            }
        };
    }

    protected function makeOrder(string $paymentId): VoucherOrder
    {
        return VoucherOrder::create([
            'delivery_type'  => 'digital',
            'face_value_cents' => 5000,
            'total_cents'    => 5000,
            'currency'       => 'EUR',
            'vat_mode'       => 'multi_purpose',
            'status'         => 'pending',
            'firstname'      => 'Test',
            'email'          => 'buyer@example.test',
            'provider'       => 'mollie',
            'payment_id'     => $paymentId,
            'payment_status' => 'open',
        ]);
    }

    public function testPaidWebhookIssuesExactlyOneVoucherAndIsIdempotent()
    {
        Mail::fake();
        $order = $this->makeOrder('tr_paid');
        PaymentService::setClientResolver(fn () => $this->fakeClient('paid', $order->id, 'tr_paid'));

        PaymentService::handleWebhook('tr_paid');
        PaymentService::handleWebhook('tr_paid'); // a retry must not issue a second voucher

        $order = $order->fresh();
        $this->assertSame('issued', $order->status);
        $this->assertSame(1, $order->vouchers()->count());

        $voucher = $order->vouchers()->first();
        $this->assertSame('active', $voucher->status);
        $this->assertSame(5000, (int) $voucher->balance_cents);
    }

    public function testFailedWebhookIssuesNoVoucher()
    {
        Mail::fake();
        $order = $this->makeOrder('tr_failed');
        PaymentService::setClientResolver(fn () => $this->fakeClient('failed', $order->id, 'tr_failed'));

        PaymentService::handleWebhook('tr_failed');

        $order = $order->fresh();
        $this->assertSame('failed', $order->status);
        $this->assertSame(0, $order->vouchers()->count());
    }
}
