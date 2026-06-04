<?php namespace JumpLink\Vouchers\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * Optional shipping address on the voucher itself, for vouchers created manually
 * in the backend or at the till (which have no online order to carry an address).
 * A physical card is usually taken along, but a phone order may need to be
 * shipped — so these stay optional. Online purchases keep their address on the
 * VoucherOrder.
 */
class AddAddressToVouchers extends Migration
{
    public function up()
    {
        Schema::table('jumplink_vouchers_vouchers', function ($table) {
            $table->string('street')->nullable()->after('recipient_name');
            $table->string('zip', 16)->nullable()->after('street');
            $table->string('city')->nullable()->after('zip');
        });
    }

    public function down()
    {
        Schema::table('jumplink_vouchers_vouchers', function ($table) {
            $table->dropColumn(['street', 'zip', 'city']);
        });
    }
}
