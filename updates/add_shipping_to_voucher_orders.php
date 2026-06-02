<?php namespace JumpLink\Vouchers\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * Shipping state for physical (posted) vouchers: when the pre-printed card was
 * mailed and by whom. `shipped_at` being null means a paid/issued physical order
 * still needs to go out (it drives the backend "open fulfillment" counter).
 */
class AddShippingToVoucherOrders extends Migration
{
    public function up()
    {
        Schema::table('jumplink_vouchers_voucher_orders', function ($table) {
            $table->dateTime('shipped_at')->nullable();
            $table->integer('shipped_by')->unsigned()->nullable();
        });
    }

    public function down()
    {
        Schema::table('jumplink_vouchers_voucher_orders', function ($table) {
            $table->dropColumn(['shipped_at', 'shipped_by']);
        });
    }
}
