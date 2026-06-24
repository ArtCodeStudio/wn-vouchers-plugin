<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;
use JumpLink\Vouchers\Classes\PaymentService;
use JumpLink\Vouchers\Classes\PurchaseService;
use JumpLink\Vouchers\Classes\IssuanceService;

/**
 * Bank transfer ("Vorkasse") payment option. The buy form offers it alongside
 * Mollie (gated by config), the order is created with provider=banktransfer, and
 * the voucher is issued only when staff confirm the payment — via the same
 * IssuanceService path as the Mollie webhook.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class BankTransferTest extends PluginTestCase
{
    protected function purchaseInput(array $overrides = []): array
    {
        return array_merge([
            'face_value'    => '50',
            'delivery_type' => 'digital',
            'firstname'     => 'Test',
            'email'         => 'bt-' . uniqid() . '@example.test',
        ], $overrides);
    }

    //
    // availableMethods() resolver
    //
    public function testModeBankTransferOffersOnlyTransferEvenWithKey()
    {
        Settings::set('payment_mode', 'banktransfer');
        $this->assertSame(['banktransfer'], PaymentService::availableMethods());
    }

    public function testModeBothWithConfiguredKeyOffersBoth()
    {
        Settings::set('payment_mode', 'both');
        // The test app env has a Mollie test key configured.
        $this->assertTrue(PaymentService::isConfigured(), 'precondition: Mollie key configured');
        $this->assertSame(['mollie', 'banktransfer'], PaymentService::availableMethods());
    }

    public function testMollieIsDroppedAndTransferUsedWhenKeyMissing()
    {
        Settings::set('payment_mode', 'both');

        $key = getenv('MOLLIE_API_KEY');
        putenv('MOLLIE_API_KEY=');
        unset($_ENV['MOLLIE_API_KEY'], $_SERVER['MOLLIE_API_KEY']);
        try {
            if (PaymentService::isConfigured()) {
                $this->markTestSkipped('MOLLIE_API_KEY could not be unset in this environment.');
            }
            // No key -> Mollie dropped, bank transfer remains (also covers mode 'mollie').
            $this->assertSame(['banktransfer'], PaymentService::availableMethods());
            Settings::set('payment_mode', 'mollie');
            $this->assertSame(['banktransfer'], PaymentService::availableMethods());
        } finally {
            if ($key !== false && $key !== '') {
                putenv("MOLLIE_API_KEY=$key");
                $_ENV['MOLLIE_API_KEY'] = $key;
                $_SERVER['MOLLIE_API_KEY'] = $key;
            }
        }
    }

    //
    // createPendingOrder() provider routing
    //
    public function testPurchaseHonoursChosenBankTransfer()
    {
        Settings::set('payment_mode', 'both');
        $result = PurchaseService::createPendingOrder($this->purchaseInput(['payment_method' => 'banktransfer']));

        $this->assertTrue($result['success']);
        $this->assertSame('banktransfer', $result['order']->provider);
        $this->assertSame('pending', $result['order']->status);
    }

    public function testPurchaseDefaultsToFirstAvailableWhenNoneChosen()
    {
        Settings::set('payment_mode', 'banktransfer');
        $result = PurchaseService::createPendingOrder($this->purchaseInput());

        $this->assertSame('banktransfer', $result['order']->provider);
    }

    public function testPurchaseIgnoresUnavailableMethodAndFallsBack()
    {
        // Only bank transfer offered, but the request asks for mollie -> fall back.
        Settings::set('payment_mode', 'banktransfer');
        $result = PurchaseService::createPendingOrder($this->purchaseInput(['payment_method' => 'mollie']));

        $this->assertSame('banktransfer', $result['order']->provider);
    }

    //
    // Issuance derives the voucher payment_method from the order provider
    //
    public function testBankTransferOrderIssuesWithBankTransferMethod()
    {
        $order = VoucherOrder::create([
            'delivery_type'    => 'digital',
            'face_value_cents' => 5000,
            'total_cents'      => 5000,
            'currency'         => 'EUR',
            'vat_mode'         => 'multi_purpose',
            'status'           => 'pending',
            'firstname'        => 'Test',
            'email'            => 'bt-issue@example.test',
            'provider'         => 'banktransfer',
        ]);

        $voucher = IssuanceService::issueForOrder($order)['voucher'];

        $this->assertSame('banktransfer', $voucher->payment_method);
        $this->assertSame('paid', $voucher->payment_status);
        $this->assertSame('issued', $order->fresh()->status);
    }

    public function testMollieOrderIssuesWithOnlineMethod()
    {
        $order = VoucherOrder::create([
            'delivery_type'    => 'digital',
            'face_value_cents' => 5000,
            'total_cents'      => 5000,
            'currency'         => 'EUR',
            'vat_mode'         => 'multi_purpose',
            'status'           => 'pending',
            'firstname'        => 'Test',
            'email'            => 'mollie-issue@example.test',
            'provider'         => 'mollie',
        ]);

        $voucher = IssuanceService::issueForOrder($order)['voucher'];

        $this->assertSame('online', $voucher->payment_method);
    }

    public function testTransferReferenceIsStable()
    {
        $order = VoucherOrder::create([
            'delivery_type'    => 'digital',
            'face_value_cents' => 5000,
            'total_cents'      => 5000,
            'status'           => 'pending',
            'firstname'        => 'Test',
            'email'            => 'bt-ref@example.test',
            'provider'         => 'banktransfer',
        ]);

        $this->assertSame('GS-' . $order->id, $order->transfer_reference);
        $this->assertTrue($order->isBankTransfer());
        $this->assertTrue($order->awaitingTransfer());
    }

    //
    // Backend menu counter: bank transfers awaiting payment
    //
    public function testAwaitingPaymentCounterCountsPendingTransfersAndClearsOnIssue()
    {
        $this->assertSame(0, VoucherOrder::awaitingPaymentCount());

        $order = VoucherOrder::create([
            'delivery_type'    => 'digital',
            'face_value_cents' => 5000,
            'total_cents'      => 5000,
            'status'           => 'pending',
            'firstname'        => 'Test',
            'email'            => 'bt-count@example.test',
            'provider'         => 'banktransfer',
        ]);

        // A pending transfer is an open task...
        $this->assertSame(1, VoucherOrder::awaitingPaymentCount());
        $this->assertSame(1, VoucherOrder::openActionCount());

        // ...and drops out once the payment is confirmed (voucher issued).
        IssuanceService::issueForOrder($order);
        $this->assertSame(0, VoucherOrder::awaitingPaymentCount());
        $this->assertSame(0, VoucherOrder::openActionCount());
    }

    public function testMolliePendingOrderIsNotAnAwaitingPaymentTask()
    {
        // An unpaid Mollie order is not a manual task — the webhook handles it.
        VoucherOrder::create([
            'delivery_type'    => 'digital',
            'face_value_cents' => 5000,
            'total_cents'      => 5000,
            'status'           => 'pending',
            'firstname'        => 'Test',
            'email'            => 'mollie-pending@example.test',
            'provider'         => 'mollie',
        ]);

        $this->assertSame(0, VoucherOrder::awaitingPaymentCount());
    }
}
