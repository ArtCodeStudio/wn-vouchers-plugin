<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Classes\RedemptionService;
use JumpLink\Vouchers\Classes\VoucherCode;

/**
 * Ledger / partial-redemption invariants for the voucher balance.
 *
 * Run from the app root:  php artisan winter:test -p JumpLink.Vouchers
 */
class RedemptionServiceTest extends PluginTestCase
{
    protected function makeVoucher(int $initialCents): Voucher
    {
        return Voucher::create([
            'code'                => VoucherCode::format(900000 + random_int(1, 99999)),
            'number_source'       => 'auto',
            'type'                => 'digital',
            'initial_value_cents' => $initialCents,
            'balance_cents'       => $initialCents,
            'status'              => 'active',
            'token_secret'        => bin2hex(random_bytes(16)),
        ]);
    }

    public function testPartialRedemptionLeavesRemainingBalance()
    {
        $v = $this->makeVoucher(5000);

        $r = RedemptionService::redeem($v->id, 3000);

        $this->assertTrue($r['success']);
        $this->assertSame(2000, $r['balance_cents']);
        $this->assertSame(2000, $v->fresh()->balance_cents);
        $this->assertSame('active', $v->fresh()->status);
    }

    public function testOverRedemptionIsRejected()
    {
        $v = $this->makeVoucher(2000);

        $r = RedemptionService::redeem($v->id, 2500);

        $this->assertFalse($r['success']);
        $this->assertSame('insufficient_balance', $r['error']);
        $this->assertSame(2000, $v->fresh()->balance_cents);
        $this->assertSame(0, $v->redemptions()->count());
    }

    public function testFullRedemptionMarksRedeemed()
    {
        $v = $this->makeVoucher(2000);

        RedemptionService::redeem($v->id, 2000);

        $this->assertSame(0, $v->fresh()->balance_cents);
        $this->assertSame('redeemed', $v->fresh()->status);
    }

    public function testIdempotencyKeyPreventsDoubleRedemption()
    {
        $v = $this->makeVoucher(5000);

        $first  = RedemptionService::redeem($v->id, 2000, ['idempotency_key' => 'abc']);
        $second = RedemptionService::redeem($v->id, 2000, ['idempotency_key' => 'abc']);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['idempotent'] ?? false);
        $this->assertSame(1, $v->redemptions()->count());
        $this->assertSame(3000, $v->fresh()->balance_cents);
    }

    public function testBalanceInvariantHolds()
    {
        $v = $this->makeVoucher(10000);
        RedemptionService::redeem($v->id, 2500);
        RedemptionService::redeem($v->id, 1500);

        $v = $v->fresh();
        $this->assertSame($v->ledgerBalance(), (int) $v->balance_cents);
        $this->assertSame(6000, (int) $v->balance_cents);
    }
}
