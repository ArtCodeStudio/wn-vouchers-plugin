<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Classes\PosService;
use JumpLink\Vouchers\Classes\VoucherCode;
use JumpLink\Vouchers\Classes\QrService;

/**
 * Till lookup + on-site sale. resolveVoucher must accept a typed code, a bare
 * signed token and a full scan URL, while rejecting typos, unknown codes and
 * tampered tokens.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class PosServiceTest extends PluginTestCase
{
    protected function makeVoucher(int $number = 100500): Voucher
    {
        return Voucher::create([
            'number_source' => 'manual',
            'number'        => $number,
            'type'          => 'digital',
            'value_euro'    => '50,00',
        ]);
    }

    public function testResolveByTypedCode()
    {
        $v = $this->makeVoucher(100500);

        $found = PosService::resolveVoucher($v->code);
        $this->assertNotNull($found);
        $this->assertSame($v->id, $found->id);

        // Case-insensitive / trims whitespace.
        $this->assertNotNull(PosService::resolveVoucher('  ' . strtolower($v->code) . ' '));
    }

    public function testResolveRejectsTypoAndUnknownCode()
    {
        $this->makeVoucher(100500);

        $this->assertNull(PosService::resolveVoucher('MAM-100500-Z')); // wrong check char
        $this->assertNull(PosService::resolveVoucher(VoucherCode::format(999999))); // valid format, no such voucher
        $this->assertNull(PosService::resolveVoucher(''));
    }

    public function testResolveBySignedTokenAndScanUrl()
    {
        $v = $this->makeVoucher(100501);
        $token = VoucherCode::buildToken($v->id, $v->token_secret);

        // Bare token.
        $this->assertSame($v->id, PosService::resolveVoucher($token)->id);

        // Full scan URL (what the QR actually encodes).
        $url = QrService::scanUrl($v->id, $v->token_secret);
        $this->assertSame($v->id, PosService::resolveVoucher($url)->id);
    }

    public function testResolveByBareNumber()
    {
        $v = $this->makeVoucher(100510);

        // The card only prints the number, so the till must accept it too.
        $found = PosService::resolveVoucher((string) $v->number);
        $this->assertNotNull($found);
        $this->assertSame($v->id, $found->id);

        $this->assertNull(PosService::resolveVoucher('999999')); // no such number
    }

    public function testResolveRejectsTamperedToken()
    {
        $v = $this->makeVoucher(100502);
        $token = VoucherCode::buildToken($v->id, $v->token_secret);

        $this->assertNull(PosService::resolveVoucher($token . 'x'));
    }

    public function testSellCreatesActivePaidVoucher()
    {
        $result = PosService::sell([
            'value_euro'     => '40,00',
            'type'           => 'physical',
            'payment_method' => 'card',
            'number'         => 100600,
        ], 5);

        $this->assertTrue($result['success']);
        $voucher = $result['voucher'];
        $this->assertSame('active', $voucher->status);
        $this->assertSame('paid', $voucher->payment_status);
        $this->assertSame('card', $voucher->payment_method);
        $this->assertSame(4000, (int) $voucher->balance_cents);
        $this->assertSame('physical', $voucher->type);
        $this->assertSame(5, (int) $voucher->created_by);
        $this->assertTrue(VoucherCode::isValid($voucher->code));
    }

    public function testSellRejectsZeroAmount()
    {
        $result = PosService::sell(['value_euro' => '0', 'type' => 'physical'], 1);
        $this->assertFalse($result['success']);
    }

    public function testSellDigitalRequiresValidEmail()
    {
        $this->assertFalse(PosService::sell(['value_euro' => '20,00', 'type' => 'digital'], 1)['success']);
        $this->assertFalse(PosService::sell(['value_euro' => '20,00', 'type' => 'digital', 'email' => 'nope'], 1)['success']);

        $ok = PosService::sell(['value_euro' => '20,00', 'type' => 'digital', 'email' => 'kunde@example.test'], 1);
        $this->assertTrue($ok['success']);
        $this->assertSame('digital', $ok['voucher']->type);
    }

    public function testSellPhysicalNeedsNoEmail()
    {
        $result = PosService::sell(['value_euro' => '20,00', 'type' => 'physical'], 1);
        $this->assertTrue($result['success']);
        $this->assertSame('physical', $result['voucher']->type);
    }
}
