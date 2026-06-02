<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\Settings;
use JumpLink\Vouchers\Classes\VoucherCode;

/**
 * Backend voucher creation conveniences: staff enter a number + euro value, and
 * the code / token / balance are derived automatically.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class VoucherCreationTest extends PluginTestCase
{
    public function testManualVoucherDerivesCodeFromNumber()
    {
        $v = Voucher::create([
            'number_source' => 'manual',
            'number'        => 100123,
            'type'          => 'digital',
            'value_euro'    => '50,00',
            'recipient_name' => 'Familie Mustermann',
        ]);

        $this->assertSame(VoucherCode::format(100123), $v->code);
        $this->assertTrue(VoucherCode::isValid($v->code));
        $this->assertSame(5000, (int) $v->initial_value_cents);
        $this->assertSame(5000, (int) $v->balance_cents);
        $this->assertSame('active', $v->status);
        $this->assertNotEmpty($v->token_secret);
    }

    public function testAutoVoucherAllocatesNumberAndCode()
    {
        Settings::set('voucher_start_number', 700000);

        $v = Voucher::create([
            'number_source' => 'auto',
            'type'          => 'digital',
            'value_euro'    => '25,00',
        ]);

        $this->assertSame(700000, (int) $v->number);
        $this->assertSame(VoucherCode::format(700000), $v->code);
        $this->assertSame(2500, (int) $v->initial_value_cents);
    }

    public function testManualVoucherRecordsPayment()
    {
        $v = Voucher::create([
            'number_source'  => 'manual',
            'number'         => 100200,
            'type'           => 'physical',
            'value_euro'     => '40,00',
            'payment_method' => 'cash', // paid at the till
        ]);

        // Defaults to "paid" unless staff mark it unpaid.
        $this->assertSame('paid', $v->payment_status);
        $this->assertSame('cash', $v->payment_method);

        $unpaid = Voucher::create([
            'number_source'  => 'manual',
            'number'         => 100201,
            'type'           => 'physical',
            'value_euro'     => '40,00',
            'payment_status' => 'unpaid',
        ]);
        $this->assertSame('unpaid', $unpaid->payment_status);
    }

    public function testManualVoucherRequiresNumber()
    {
        $this->expectException(\Winter\Storm\Exception\ValidationException::class);

        Voucher::create([
            'number_source' => 'manual',
            'type'          => 'digital',
            'value_euro'    => '10,00',
        ]);
    }

    public function testDefaultExpiryIsTheConfiguredYearsToYearEnd()
    {
        Settings::set('default_validity_years', 3);

        $v = Voucher::create([
            'number_source' => 'manual',
            'number'        => 100300,
            'type'          => 'digital',
            'value_euro'    => '10,00',
        ]);

        $expectedYear = ((int) date('Y')) + 3;
        $this->assertNotNull($v->valid_until);
        $this->assertSame($expectedYear . '-12-31', $v->valid_until->format('Y-m-d'));
    }

    public function testZeroValidityYearsMeansNoExpiry()
    {
        Settings::set('default_validity_years', 0);

        $v = Voucher::create([
            'number_source' => 'manual',
            'number'        => 100301,
            'type'          => 'digital',
            'value_euro'    => '10,00',
        ]);

        $this->assertNull($v->valid_until);
    }

    public function testExplicitExpiryIsKept()
    {
        Settings::set('default_validity_years', 3);

        $v = Voucher::create([
            'number_source' => 'manual',
            'number'        => 100302,
            'type'          => 'digital',
            'value_euro'    => '10,00',
            'valid_until'   => '2027-06-15',
        ]);

        $this->assertSame('2027-06-15', $v->valid_until->format('Y-m-d'));
    }

    public function testDistinctRecipientsDeduplicates()
    {
        foreach ([['Anna', 1], ['Bert', 2], ['Anna', 3]] as [$name, $n]) {
            Voucher::create([
                'number_source'  => 'manual',
                'number'         => $n,
                'type'           => 'digital',
                'value_euro'     => '10,00',
                'recipient_name' => $name,
            ]);
        }

        $recipients = Voucher::distinctRecipients();

        $this->assertContains('Anna', $recipients);
        $this->assertContains('Bert', $recipients);
        $this->assertCount(2, $recipients);
    }
}
