<?php namespace JumpLink\Vouchers\Tests\Unit;

use System\Tests\Bootstrap\PluginTestCase;
use JumpLink\Vouchers\Classes\VoucherCode;

/**
 * Code formatting (check character) and the signed redemption token.
 */
class VoucherCodeTest extends PluginTestCase
{
    public function testCodeFormatAndCheckCharacter()
    {
        $code = VoucherCode::format(100042);

        $this->assertStringStartsWith('MAM-100042-', $code);
        $this->assertTrue(VoucherCode::isValid($code));
        // Wrong check character must be rejected.
        $this->assertFalse(VoucherCode::isValid('MAM-100042-#'));
    }

    public function testSignedTokenRoundTripAndTamperRejection()
    {
        $secret = bin2hex(random_bytes(16));
        $token  = VoucherCode::buildToken(42, $secret);

        $resolver = function ($id) use ($secret) {
            return $id === 42 ? $secret : null;
        };

        $this->assertSame(42, VoucherCode::verifyToken($token, $resolver));
        $this->assertNull(VoucherCode::verifyToken($token . 'x', $resolver));
        // A token for the wrong voucher secret must not verify.
        $this->assertNull(VoucherCode::verifyToken($token, function ($id) {
            return 'different-secret';
        }));
    }
}
