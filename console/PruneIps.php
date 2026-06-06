<?php namespace JumpLink\Vouchers\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;

/**
 * GDPR data minimisation. The buyer IP is captured on each order for abuse
 * auditing; this command nulls it once the order is older than the configured
 * retention window (Settings → ip_retention_days, default 90; 0 disables it).
 * Fiscal fields (amount, payment id) are untouched. Scheduled daily by the
 * plugin (registerSchedule), or run manually / overridden with --days.
 *
 *   php artisan jumplink:vouchers-prune-ips
 *   php artisan jumplink:vouchers-prune-ips --days=30
 */
class PruneIps extends Command
{
    protected $signature = 'jumplink:vouchers-prune-ips {--days= : Override the retention window in days}';

    protected $description = 'GDPR: delete the stored buyer IP on orders older than the retention window.';

    public function handle()
    {
        $days = (int) ($this->option('days') !== null ? $this->option('days') : Settings::get('ip_retention_days', 90));
        if ($days <= 0) {
            $this->info('IP retention disabled (0 days) — nothing pruned.');
            return 0;
        }

        $cutoff = Carbon::now()->subDays($days);
        $count = VoucherOrder::whereNotNull('ip')
            ->where('created_at', '<', $cutoff)
            ->update(['ip' => null]);

        $this->info("Pruned the stored IP from {$count} order(s) older than {$days} days.");
        return 0;
    }
}
