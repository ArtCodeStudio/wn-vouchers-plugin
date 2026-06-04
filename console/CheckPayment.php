<?php namespace JumpLink\Vouchers\Console;

use Illuminate\Console\Command;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Classes\PaymentService;

/**
 * Re-fetch a Mollie payment by id and run the webhook logic (issue the voucher
 * if the payment is paid). Two uses:
 *   - LOCAL DEV: there is no public webhook URL, so after paying in the Mollie
 *     test checkout, run this to issue the voucher (replaces Mollie's callback).
 *   - PRODUCTION: recover an order whose webhook delivery was missed.
 *
 *   php artisan jumplink:vouchers-check-payment 42   # a specific order
 *   php artisan jumplink:vouchers-check-payment      # latest not-yet-issued
 */
class CheckPayment extends Command
{
    protected $signature = 'jumplink:vouchers-check-payment {order? : Order id (default: latest order with a payment that is not yet issued)}';

    protected $description = 'Re-fetch a Mollie payment and issue the voucher if paid (re-runs the webhook; for local testing or to recover a missed webhook).';

    public function handle()
    {
        $order = $this->argument('order')
            ? VoucherOrder::find($this->argument('order'))
            : VoucherOrder::whereNotNull('payment_id')->where('status', '!=', 'issued')->orderByDesc('id')->first();

        if (!$order) {
            $this->error('No matching order found.');
            return 1;
        }
        if (!$order->payment_id) {
            $this->error("Order {$order->id} has no payment_id (payment not started).");
            return 1;
        }
        if (!PaymentService::isConfigured()) {
            $this->error('MOLLIE_API_KEY is not set.');
            return 1;
        }

        $this->info("Order {$order->id}: payment {$order->payment_id} (status {$order->status}) — re-running webhook…");
        PaymentService::handleWebhook($order->payment_id);

        $order = $order->fresh();
        $count = Voucher::where('order_id', $order->id)->count();
        $this->info("-> order status: {$order->status}, payment_status: {$order->payment_status}, vouchers issued: {$count}.");
        return 0;
    }
}
