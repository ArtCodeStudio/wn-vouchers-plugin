<?php namespace JumpLink\Vouchers\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * Drop the never-populated `vat_breakdown` column from the redemption ledger.
 *
 * VAT on a multi-purpose voucher (Mehrzweckgutschein) is due at redemption, but
 * that 7%/19% split is recorded by the restaurant's (TSE) cash register when the
 * meal is rung up — the voucher is only the tender. The till never captured a
 * breakdown here, so the column was always empty; removing it keeps the ledger
 * focused on the running balance.
 */
class DropVatBreakdownFromRedemptions extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('jumplink_vouchers_redemptions', 'vat_breakdown')) {
            Schema::table('jumplink_vouchers_redemptions', function ($table) {
                $table->dropColumn('vat_breakdown');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasColumn('jumplink_vouchers_redemptions', 'vat_breakdown')) {
            Schema::table('jumplink_vouchers_redemptions', function ($table) {
                $table->mediumText('vat_breakdown')->nullable();
            });
        }
    }
}
