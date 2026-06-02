<?php namespace JumpLink\Vouchers\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * Payment state for manually-created vouchers. Online vouchers carry their
 * payment on the order; binder/till vouchers have no order, so they record here
 * whether and how the customer paid — e.g. already at the normal till, outside
 * this system.
 */
class AddPaymentToVouchers extends Migration
{
    public function up()
    {
        Schema::table('jumplink_vouchers_vouchers', function ($table) {
            $table->string('payment_status', 16)->default('paid'); // paid | unpaid
            $table->string('payment_method', 16)->nullable();       // pos | cash | card | invoice | online | other
        });
    }

    public function down()
    {
        Schema::table('jumplink_vouchers_vouchers', function ($table) {
            $table->dropColumn(['payment_status', 'payment_method']);
        });
    }
}
