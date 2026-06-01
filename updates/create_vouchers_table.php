<?php namespace JumpLink\Vouchers\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * The voucher itself. Issued after payment (digital) or created manually by
 * staff (physical pre-printed cards). `balance_cents` is a cache; the
 * redemptions ledger is the source of truth.
 */
class CreateVouchersTable extends Migration
{
    public function up()
    {
        Schema::create('jumplink_vouchers_vouchers', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('order_id')->unsigned()->nullable()->index(); // null for till/manual

            $table->string('code', 40)->unique();             // human-readable, e.g. MAM-100042-K
            $table->bigInteger('number')->unsigned()->nullable()->index();
            $table->string('number_source', 8)->default('auto'); // auto | manual
            $table->string('type', 16)->default('digital');      // digital | physical

            $table->integer('initial_value_cents')->unsigned()->default(0);
            $table->integer('balance_cents')->unsigned()->default(0); // cached; ledger = truth
            $table->string('currency', 3)->default('EUR');
            $table->string('vat_mode', 16)->default('multi_purpose');

            $table->string('status', 16)->default('active');     // active | redeemed | void | expired
            $table->string('token_secret', 64)->nullable();      // per-voucher secret for the signed QR token
            $table->string('recipient_name')->nullable();
            $table->date('valid_until')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('pdf_generated_at')->nullable();
            $table->integer('created_by')->unsigned()->nullable(); // backend user id if staff-created
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jumplink_vouchers_vouchers');
    }
}
