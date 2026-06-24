<?php namespace JumpLink\Vouchers\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use JumpLink\Vouchers\Classes\DatevExportService;
use JumpLink\Vouchers\Models\Settings;

/**
 * Export voucher sales as a DATEV-Format booking batch (EXTF v700) the operator
 * can hand to their own accounting / tax advisor. Bank- and software-neutral; the
 * account numbers come from Settings → Beleg → DATEV.
 *
 *   php artisan jumplink:vouchers-datev-export --year=2026
 *   php artisan jumplink:vouchers-datev-export --from=2026-01-01 --to=2026-03-31 --output=/tmp/gs.csv
 *
 * Without --output the CSV is written to storage/app/ and the path is printed.
 */
class DatevExport extends Command
{
    protected $signature = 'jumplink:vouchers-datev-export
        {--year= : Calendar year to export (default: current year)}
        {--from= : Start date YYYY-MM-DD (overrides --year)}
        {--to= : End date YYYY-MM-DD (overrides --year)}
        {--output= : Write the CSV to this path (default: storage/app/)}';

    protected $description = 'Export voucher sales as a DATEV-Format booking batch (EXTF) CSV.';

    public function handle()
    {
        if ($this->option('from') && $this->option('to')) {
            try {
                $from = Carbon::createFromFormat('Y-m-d', $this->option('from'))->startOfDay();
                $to   = Carbon::createFromFormat('Y-m-d', $this->option('to'))->endOfDay();
            } catch (\Throwable $e) {
                $this->error('Invalid --from/--to date — use the format YYYY-MM-DD.');
                return 1;
            }
        } else {
            $year = (int) ($this->option('year') ?: Carbon::now()->format('Y'));
            $from = Carbon::create($year, 1, 1)->startOfDay();
            $to   = Carbon::create($year, 12, 31)->endOfDay();
        }

        if (trim((string) Settings::get('datev_money_account', '')) === ''
            || trim((string) Settings::get('datev_voucher_liability_account', '')) === '') {
            $this->warn('[WARN] DATEV account numbers are not set (Settings → Beleg → DATEV) — Konto/Gegenkonto will be empty and the file cannot be imported as-is.');
        }

        $orders = DatevExportService::bookableOrders($from, $to);
        $csv    = DatevExportService::export($orders, $from, $to);

        $path = $this->option('output')
            ?: storage_path('app/datev-gutscheine-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv');

        file_put_contents($path, $csv);

        $this->info("Exported {$orders->count()} voucher sale(s) to {$path}");
        return 0;
    }
}
