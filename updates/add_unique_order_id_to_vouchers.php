<?php namespace JumpLink\Vouchers\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * One online order issues exactly one voucher. A unique index on order_id makes
 * a second issuance for the same paid order fail at the database level — the
 * hard backstop behind IssuanceService's "voucher already exists" check. NULL
 * order_id (till/manually-created vouchers) is exempt: SQL allows many NULLs in
 * a unique index, so on-site sales are unaffected.
 */
class AddUniqueOrderIdToVouchers extends Migration
{
    public function up()
    {
        Schema::table('jumplink_vouchers_vouchers', function ($table) {
            $table->unique('order_id', 'jumplink_vouchers_vouchers_order_id_unique');
        });
    }

    public function down()
    {
        Schema::table('jumplink_vouchers_vouchers', function ($table) {
            $table->dropUnique('jumplink_vouchers_vouchers_order_id_unique');
        });
    }
}
