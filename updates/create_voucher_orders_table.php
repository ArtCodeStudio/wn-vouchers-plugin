<?php namespace JumpLink\Vouchers\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * Purchase / payment record. Created `pending` before payment; produces
 * voucher(s) only after the Mollie webhook confirms `paid`.
 */
class CreateVoucherOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('jumplink_vouchers_voucher_orders', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');

            $table->string('delivery_type', 16)->default('digital');   // digital | physical
            $table->integer('face_value_cents')->unsigned()->default(0);
            $table->integer('service_fee_cents')->unsigned()->default(0); // 250 only for physical
            $table->integer('total_cents')->unsigned()->default(0);
            $table->string('currency', 3)->default('EUR');

            // VAT: multi-purpose voucher by default (no VAT at sale; due on redemption).
            $table->string('vat_mode', 16)->default('multi_purpose');  // multi_purpose | single_purpose
            $table->decimal('vat_rate', 4, 2)->nullable();             // only for single_purpose

            $table->string('status', 20)->default('pending');         // pending|paid|issued|failed|cancelled|expired|refunded

            $table->string('firstname');
            $table->string('lastname')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('street')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('DE');
            $table->string('recipient_name')->nullable();
            $table->text('message')->nullable();

            $table->string('provider', 20)->default('mollie');
            $table->string('payment_id')->nullable()->index();
            $table->string('payment_status', 20)->nullable();
            $table->dateTime('paid_at')->nullable();

            // Reserved for later buchhaltung reconciliation (no code yet).
            $table->string('accounting_ref')->nullable()->index();
            $table->dateTime('accounting_synced_at')->nullable();

            $table->string('ip', 45)->nullable();                     // abuse audit; nulled by jumplink:vouchers-prune-ips after ip_retention_days
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jumplink_vouchers_voucher_orders');
    }
}
