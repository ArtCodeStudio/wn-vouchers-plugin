<?php namespace JumpLink\Vouchers\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * Per-order, unguessable access token. The post-payment return page is reached
 * with an enumerable order id, so it must additionally prove possession of this
 * token before it exposes order state or mints a signed PDF download link —
 * otherwise order ids could be enumerated to fetch other buyers' vouchers (IDOR).
 */
class AddAccessTokenToVoucherOrders extends Migration
{
    public function up()
    {
        Schema::table('jumplink_vouchers_voucher_orders', function ($table) {
            $table->string('access_token', 64)->nullable()->index();
        });
    }

    public function down()
    {
        Schema::table('jumplink_vouchers_voucher_orders', function ($table) {
            $table->dropColumn('access_token');
        });
    }
}
