<?php namespace JumpLink\Vouchers\Tests\Unit;

use Carbon\Carbon;
use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;
use JumpLink\Vouchers\Classes\DatevExportService;

/**
 * DATEV-Format booking-batch (EXTF v700) export of voucher sales. Verifies the
 * header, the fixed column layout and the per-order booking (money account Soll,
 * voucher-liability Haben, no VAT). Output is Windows-1252; tests decode it back.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class DatevExportServiceTest extends PluginTestCase
{
    protected function issuedOrder(array $overrides = []): VoucherOrder
    {
        return VoucherOrder::create(array_merge([
            'delivery_type'    => 'digital',
            'face_value_cents' => 5000,
            'total_cents'      => 5000,
            'currency'         => 'EUR',
            'vat_mode'         => 'multi_purpose',
            'status'           => 'issued',
            'firstname'        => 'Test',
            'email'            => 'datev-' . uniqid() . '@example.test',
            'provider'         => 'banktransfer',
            'paid_at'          => Carbon::create(2026, 3, 5, 12, 0, 0),
        ], $overrides));
    }

    protected function decode(string $csv): string
    {
        return mb_convert_encoding($csv, 'UTF-8', 'Windows-1252');
    }

    public function testCaptionRowHas125Columns()
    {
        $this->assertCount(125, DatevExportService::captions());
    }

    public function testHeaderIdentifiesAnExtfBookingBatch()
    {
        Settings::set(['datev_money_account' => '1200', 'datev_voucher_liability_account' => '1604']);
        $from = Carbon::create(2026, 1, 1)->startOfDay();
        $to   = Carbon::create(2026, 12, 31)->endOfDay();

        $csv  = $this->decode(DatevExportService::export([], $from, $to, Carbon::create(2026, 6, 24, 10, 0, 0)));
        $line = strtok($csv, "\r\n");

        $this->assertStringStartsWith('"EXTF";700;21;"Buchungsstapel"', $line);
        $this->assertStringContainsString(';20260101;', $line); // WJ-Beginn
        $this->assertStringContainsString('"EUR"', $line);
    }

    public function testBookingRowMapsMoneyAccountSollToLiabilityHaben()
    {
        Settings::set(['datev_money_account' => '1200', 'datev_voucher_liability_account' => '1604']);
        $order = $this->issuedOrder(['total_cents' => 5000]);

        $from = Carbon::create(2026, 1, 1)->startOfDay();
        $to   = Carbon::create(2026, 12, 31)->endOfDay();
        $csv  = $this->decode(DatevExportService::export([$order], $from, $to, Carbon::create(2026, 6, 24)));

        $rows = explode("\r\n", trim($csv));
        $this->assertCount(3, $rows); // header + captions + 1 booking
        $cells = explode(';', $rows[2]);

        $this->assertCount(125, $cells);
        $this->assertSame('50,00', $cells[0]);                 // Umsatz, comma decimal, positive
        $this->assertSame('"S"', $cells[1]);                   // Soll on the money account
        $this->assertSame('1200', $cells[6]);                  // Konto
        $this->assertSame('1604', $cells[7]);                  // Gegenkonto
        $this->assertSame('', $cells[8]);                      // BU-Schlüssel empty (no VAT)
        $this->assertSame('0503', $cells[9]);                  // Belegdatum DDMM (05.03.)
        $this->assertSame('"' . $order->receipt_number . '"', $cells[10]); // Belegfeld 1
        $this->assertStringContainsString('Mehrzweckgutschein', $cells[13]); // Buchungstext
    }

    public function testPhysicalOrderSplitsShippingFeeToItsOwnTaxedLineWhenConfigured()
    {
        Settings::set([
            'datev_money_account'             => '1200',
            'datev_voucher_liability_account' => '1604',
            'datev_shipping_revenue_account'  => '8400',
            'datev_shipping_vat_key'          => '3',
        ]);
        $order = $this->issuedOrder([
            'delivery_type'     => 'physical',
            'face_value_cents'  => 5000,
            'service_fee_cents' => 250,
            'total_cents'       => 5250,
            'street'            => 'Hauptstr. 1',
            'zip'               => '27472',
            'city'              => 'Cuxhaven',
        ]);

        $from = Carbon::create(2026, 1, 1)->startOfDay();
        $to   = Carbon::create(2026, 12, 31)->endOfDay();
        $rows = explode("\r\n", trim($this->decode(DatevExportService::export([$order], $from, $to, Carbon::create(2026, 6, 24)))));

        $this->assertCount(4, $rows); // header + captions + 2 bookings
        $voucher = explode(';', $rows[2]);
        $fee     = explode(';', $rows[3]);

        // Voucher value only (50,00 — NOT the 52,50 total) → liability, no VAT.
        $this->assertSame('50,00', $voucher[0]);
        $this->assertSame('1604', $voucher[7]);
        $this->assertSame('', $voucher[8]);

        // Shipping fee gross (2,50) → shipping revenue, 19 % VAT key.
        $this->assertSame('2,50', $fee[0]);
        $this->assertSame('"S"', $fee[1]);
        $this->assertSame('1200', $fee[6]);
        $this->assertSame('8400', $fee[7]);
        $this->assertSame('3', $fee[8]);
        $this->assertStringContainsString('Versandkosten', $fee[13]);
    }

    public function testShippingFeeStaysOnLiabilityLineWhenNoRevenueAccountConfigured()
    {
        Settings::set([
            'datev_money_account'             => '1200',
            'datev_voucher_liability_account' => '1604',
            'datev_shipping_revenue_account'  => '',   // not configured → no split
        ]);
        $order = $this->issuedOrder([
            'delivery_type'     => 'physical',
            'face_value_cents'  => 5000,
            'service_fee_cents' => 250,
            'total_cents'       => 5250,
            'street'            => 'Hauptstr. 1',
            'zip'               => '27472',
            'city'              => 'Cuxhaven',
        ]);

        $from = Carbon::create(2026, 1, 1)->startOfDay();
        $to   = Carbon::create(2026, 12, 31)->endOfDay();
        $rows = explode("\r\n", trim($this->decode(DatevExportService::export([$order], $from, $to, Carbon::create(2026, 6, 24)))));

        $this->assertCount(3, $rows); // header + captions + 1 booking (whole total)
        $cells = explode(';', $rows[2]);
        $this->assertSame('52,50', $cells[0]); // whole total stays on the liability line
        $this->assertSame('1604', $cells[7]);
        $this->assertSame('', $cells[8]);
    }

    public function testOnlyPaidIssuedOrdersInRangeAreBookable()
    {
        Settings::set(['datev_money_account' => '1200', 'datev_voucher_liability_account' => '1604']);
        $this->issuedOrder(['paid_at' => Carbon::create(2026, 5, 1)]);          // in range
        $this->issuedOrder(['paid_at' => Carbon::create(2025, 5, 1)]);          // prior year
        VoucherOrder::create([                                                  // pending, no paid_at
            'delivery_type' => 'digital', 'face_value_cents' => 5000, 'total_cents' => 5000,
            'status' => 'pending', 'firstname' => 'Pending', 'email' => 'p@example.test', 'provider' => 'banktransfer',
        ]);

        $from = Carbon::create(2026, 1, 1)->startOfDay();
        $to   = Carbon::create(2026, 12, 31)->endOfDay();
        $this->assertSame(1, DatevExportService::bookableOrders($from, $to)->count());
    }
}
