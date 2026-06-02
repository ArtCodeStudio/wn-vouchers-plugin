<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Classes\PdfService;
use JumpLink\Vouchers\Classes\QrService;
use JumpLink\Vouchers\Classes\VoucherCode;

/**
 * PDF + QR rendering. Asserts real bytes come out and that the QR encodes the
 * signed token, never the bare voucher code.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class PdfQrTest extends PluginTestCase
{
    public function testQrDataUriIsPngDataUri()
    {
        $uri = QrService::dataUri('https://example.test/scan?t=abc');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertGreaterThan(100, strlen($uri));
    }

    public function testPdfRenderReturnsPdfBytes()
    {
        $voucher = new Voucher;
        $voucher->id                  = 1234; // unsaved; render only reads attributes
        $voucher->code                = VoucherCode::format(100042);
        $voucher->initial_value_cents = 5000;
        $voucher->balance_cents       = 5000;
        $voucher->token_secret        = bin2hex(random_bytes(16));

        $pdf = PdfService::render($voucher);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function testScanUrlEncodesSignedTokenNotBareCode()
    {
        $url = QrService::scanUrl(42, bin2hex(random_bytes(16)));

        $this->assertStringContainsString('/api/jumplink/vouchers/scan', $url);
        $this->assertStringContainsString('t=', $url);
        // The signed token must not be the bare voucher id.
        $this->assertStringNotContainsString('t=42', $url);
    }
}
