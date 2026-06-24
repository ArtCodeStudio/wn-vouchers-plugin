<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;
use JumpLink\Vouchers\Classes\ReceiptService;

/**
 * Purchase receipt (Kaufbeleg). The receipt model is built as pure data so the
 * VAT logic is testable without rendering a PDF: a multi-purpose voucher sale
 * must show NO VAT (§ 3 Abs. 15 UStG) and carry the statutory note, while a
 * single-purpose sale splits net / VAT / gross.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class ReceiptServiceTest extends PluginTestCase
{
    protected function order(array $overrides = []): VoucherOrder
    {
        return VoucherOrder::create(array_merge([
            'delivery_type'    => 'digital',
            'face_value_cents' => 5000,
            'total_cents'      => 5000,
            'currency'         => 'EUR',
            'vat_mode'         => 'multi_purpose',
            'status'           => 'pending',
            'firstname'        => 'Erika',
            'lastname'         => 'Musterfrau',
            'email'            => 'r-' . uniqid() . '@example.test',
            'provider'         => 'banktransfer',
        ], $overrides));
    }

    public function testNotConfiguredWithoutSellerLegalName()
    {
        Settings::set('seller_legal_name', '');
        $this->assertFalse(ReceiptService::isConfigured());

        Settings::set('seller_legal_name', 'Test Gastro GmbH');
        $this->assertTrue(ReceiptService::isConfigured());
    }

    public function testMultiPurposeReceiptShowsNoVatAndCarriesStatutoryNote()
    {
        $order = $this->order(['vat_mode' => 'multi_purpose']);
        $model = ReceiptService::buildModel($order);

        $this->assertNull($model['vat'], 'a multi-purpose voucher sale must not show VAT');
        $this->assertSame('multi_purpose', $model['vat_mode']);
        $this->assertStringContainsString('multi_purpose', $model['note_key']);
        $this->assertSame('GS-' . $order->id, $model['number']);
        $this->assertSame(5000, $model['total_cents']);
    }

    public function testSinglePurposeReceiptSplitsNetAndVat()
    {
        $order = $this->order([
            'vat_mode'         => 'single_purpose',
            'vat_rate'         => 19,
            'face_value_cents' => 5950,
            'total_cents'      => 5950,
        ]);
        $model = ReceiptService::buildModel($order);

        $this->assertNotNull($model['vat']);
        $this->assertSame(5000, $model['vat']['net_cents']);
        $this->assertSame(950, $model['vat']['vat_cents']);
        $this->assertSame(5950, $model['vat']['gross_cents']);
        $this->assertStringContainsString('single_purpose', $model['note_key']);
    }

    public function testServiceFeeAppearsAsSeparateLineAndBuyerAddressIsCarried()
    {
        $order = $this->order([
            'delivery_type'    => 'physical',
            'face_value_cents' => 5000,
            'service_fee_cents' => 250,
            'total_cents'      => 5250,
            'street'           => 'Hauptstr. 1',
            'zip'              => '27472',
            'city'             => 'Cuxhaven',
        ]);
        $model = ReceiptService::buildModel($order);

        $this->assertCount(2, $model['lines']);
        $this->assertSame(250, $model['lines'][1]['cents']);
        $this->assertSame(5250, $model['total_cents']);
        $this->assertNotNull($model['buyer']['address']);
    }

    public function testBankTransferReferenceIsTheReceiptNumber()
    {
        $order = $this->order(['provider' => 'banktransfer']);
        $model = ReceiptService::buildModel($order);

        $this->assertSame($order->receipt_number, $model['payment']['reference']);
        $this->assertStringContainsString('banktransfer', $model['payment']['method_key']);
    }

    public function testRenderProducesPdfWhenConfigured()
    {
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $this->markTestSkipped('dompdf is not installed in this environment.');
        }
        Settings::set('seller_legal_name', 'Test Gastro GmbH');
        Settings::set('seller_address', "Hauptstr. 1\n27472 Cuxhaven");
        Settings::set('seller_tax_number', 'DE123456789');

        $pdf = ReceiptService::render($this->order());
        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
