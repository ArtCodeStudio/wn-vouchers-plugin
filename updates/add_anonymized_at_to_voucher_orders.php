<?php namespace JumpLink\Vouchers\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * GDPR: marks when an order's personal data was anonymised. The fiscal fields
 * (amount, vat, payment id, paid date) are kept for the statutory retention
 * period; only the buyer's personal data is removed (see VoucherOrder::anonymize).
 */
class AddAnonymizedAtToVoucherOrders extends Migration
{
    public function up()
    {
        Schema::table('jumplink_vouchers_voucher_orders', function ($table) {
            if (!Schema::hasColumn('jumplink_vouchers_voucher_orders', 'anonymized_at')) {
                $table->timestamp('anonymized_at')->nullable();
            }
            // firstname/email are NOT NULL by default; relax them so erasure can
            // null them. The model's `required` rules still apply to normal saves
            // (anonymize uses forceSave). The other personal columns are already
            // nullable. (->change() needs doctrine/dbal, always present via
            // winter/storm.)
            $table->string('firstname')->nullable()->change();
            $table->string('email')->nullable()->change();
        });
    }

    public function down()
    {
        // Only the added column is reversed; the nullability relaxation is left in
        // place because anonymised rows may legitimately hold NULL firstname/email.
        Schema::table('jumplink_vouchers_voucher_orders', function ($table) {
            if (Schema::hasColumn('jumplink_vouchers_voucher_orders', 'anonymized_at')) {
                $table->dropColumn('anonymized_at');
            }
        });
    }
}
