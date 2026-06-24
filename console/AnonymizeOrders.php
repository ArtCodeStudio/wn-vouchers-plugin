<?php namespace JumpLink\Vouchers\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;

/**
 * GDPR routine data minimisation: anonymise the buyer's personal data on orders
 * older than the configured window (Settings → personal_data_retention_days,
 * default 0 = disabled). The fiscal fields are kept; only personal data is nulled
 * (see VoucherOrder::anonymize). On-demand erasure requests are handled in the
 * backend; this command is the scheduled sweep. Scheduled daily by the plugin —
 * it self-disables when the retention is 0.
 *
 *   php artisan jumplink:vouchers-anonymize-orders
 *   php artisan jumplink:vouchers-anonymize-orders --days=3650 --dry-run
 */
class AnonymizeOrders extends Command
{
    protected $signature = 'jumplink:vouchers-anonymize-orders
        {--days= : Override the retention window in days}
        {--dry-run : Only report how many orders would be anonymised}';

    protected $description = 'GDPR: anonymise personal data on orders older than the retention window (fiscal fields kept).';

    public function handle()
    {
        $days = (int) ($this->option('days') !== null ? $this->option('days') : Settings::get('personal_data_retention_days', 0));
        if ($days <= 0) {
            $this->info('Personal-data retention disabled (0 days) — nothing anonymised.');
            return 0;
        }

        $cutoff = Carbon::now()->subDays($days);
        $query  = VoucherOrder::whereNull('anonymized_at')->where('created_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$query->count()} order(s) older than {$days} days would be anonymised.");
            return 0;
        }

        // chunkById (not each()) — anonymize() mutates anonymized_at, the column we
        // filter on, so offset-based iteration would shift the window and skip rows.
        // Paginating by id is stable against that mutation.
        $count = 0;
        $query->chunkById(200, function ($orders) use (&$count) {
            foreach ($orders as $order) {
                if ($order->anonymize()) {
                    $count++;
                }
            }
        });

        $this->info("Anonymised personal data on {$count} order(s) older than {$days} days.");
        return 0;
    }
}
