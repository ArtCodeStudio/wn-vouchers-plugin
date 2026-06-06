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
    protected function fakeClient(string $status, $orderId, string $paymentId, string $amountValue = '50.00')
    {
        $payment = new class($status, $orderId, $paymentId, $amountValue) {
            public $id;
            public $status;
            public $metadata;
            public $amount;
            public function __construct($status, $orderId, $paymentId, $amountValue)
            {
                $this->status = $status;
                $this->id = $paymentId;
                $this->metadata = (object) ['order_id' => $orderId];
                // Orders in this test are 5000 cents (= "50.00"); the webhook's
                // amount check passes by default and a mismatch can be injected.
                $this->amount = (object) ['value' => $amountValue, 'currency' => 'EUR'];
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

    /**
     * Resetting an issued order's status back to pending (a backend edit) must
     * not let a re-run webhook mint a second voucher for the same payment —
     * issuance is idempotent on "a voucher already exists", not on the status.
     */
    public function testReissueBlockedAfterStatusResetToPending()
    {
        Mail::fake();
        $order = $this->makeOrder('tr_reissue');
        PaymentService::setClientResolver(fn () => $this->fakeClient('paid', $order->id, 'tr_reissue'));

        PaymentService::handleWebhook('tr_reissue'); // issues voucher #1

        $reset = VoucherOrder::find($order->id);
        $reset->status = 'pending';
        $reset->save();

        PaymentService::handleWebhook('tr_reissue'); // must not issue a second

        $this->assertSame(1, VoucherOrder::find($order->id)->vouchers()->count());
        $this->assertSame('issued', VoucherOrder::find($order->id)->status);
    }

    /** A paid payment whose amount does not match the order total issues nothing. */
    public function testAmountMismatchDoesNotIssue()
    {
        Mail::fake();
        $order = $this->makeOrder('tr_mismatch'); // total 5000 cents
        PaymentService::setClientResolver(fn () => $this->fakeClient('paid', $order->id, 'tr_mismatch', '10.00'));

        PaymentService::handleWebhook('tr_mismatch');

        $order = $order->fresh();
        $this->assertSame('pending', $order->status);
        $this->assertSame(0, $order->vouchers()->count());
    }
}
