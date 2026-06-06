<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\Redemption;
use JumpLink\Vouchers\Classes\RedemptionService;
use JumpLink\Vouchers\Classes\VoucherCode;

/**
 * The backend redemption form is read-only over an immutable ledger row, but
 * `vat_breakdown` is a jsonable array — binding it straight to a textarea threw
 * "Array to string conversion" (HTTP 500). The form now binds the
 * vat_breakdown_text accessor; these tests pin that it always yields a string.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class RedemptionVatBreakdownTest extends PluginTestCase
{
    protected function makeVoucher(int $initialCents): Voucher
    {
        return Voucher::create([
            'code'                => VoucherCode::format(800000 + random_int(1, 99999)),
            'number_source'       => 'auto',
            'type'                => 'digital',
            'initial_value_cents' => $initialCents,
            'balance_cents'       => $initialCents,
            'status'              => 'active',
            'token_secret'        => bin2hex(random_bytes(16)),
        ]);
    }

    public function testStoredVatBreakdownRendersAsStringForTheForm()
    {
        $v = $this->makeVoucher(5000);
        $breakdown = [
            ['rate' => 7, 'net_cents' => 1308, 'vat_cents' => 92, 'gross_cents' => 1400],
            ['rate' => 19, 'net_cents' => 4202, 'vat_cents' => 798, 'gross_cents' => 5000],
        ];

        RedemptionService::redeem($v->id, 1400, ['vat_breakdown' => $breakdown]);

        // Reload from the DB so vat_breakdown comes back as the persisted jsonable
        // array — exactly the value that used to blow up the textarea widget.
        $redemption = Redemption::where('voucher_id', $v->id)->firstOrFail();
        $this->assertIsArray($redemption->vat_breakdown);

        $text = $redemption->vat_breakdown_text;
        $this->assertIsString($text, 'the form binds a string, never the raw array');
        $this->assertStringContainsString('7', $text);
        $this->assertStringContainsString('14,00', $text);
        $this->assertStringContainsString('19', $text);
    }

    public function testNoVatBreakdownYieldsEmptyString()
    {
        $v = $this->makeVoucher(5000);
        RedemptionService::redeem($v->id, 1000); // no vat_breakdown opt

        $redemption = Redemption::where('voucher_id', $v->id)->firstOrFail();
        $this->assertSame('', $redemption->vat_breakdown_text);
    }
}
