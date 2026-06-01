<?php namespace JumpLink\Vouchers\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * Append-only redemption ledger. Each (partial) redemption is one immutable
 * row; balance is derived from SUM(amount_cents). `vat_breakdown` captures the
 * 7%/19% split selected at the till (multi-purpose voucher → VAT on redemption).
 */
class CreateRedemptionsTable extends Migration
{
    public function up()
    {
        Schema::create('jumplink_vouchers_redemptions', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('voucher_id')->unsigned()->index();

            $table->integer('amount_cents');                 // + redeemed, - reversal/correction
            $table->integer('balance_after_cents')->unsigned()->default(0);
            $table->string('kind', 16)->default('redeem');   // redeem | reversal | adjust
            $table->mediumText('vat_breakdown')->nullable(); // JSON: [{rate, net_cents, vat_cents, gross_cents}]
            $table->string('note')->nullable();
            $table->integer('redeemed_by')->unsigned()->nullable(); // backend user id
            $table->string('source', 16)->default('pos');    // pos | backend | api
            $table->string('idempotency_key', 64)->nullable()->unique();

            // Ledger rows are immutable -> created_at only (no updated_at).
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jumplink_vouchers_redemptions');
    }
}
