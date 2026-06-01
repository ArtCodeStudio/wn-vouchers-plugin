<?php namespace JumpLink\Vouchers\Console;

use Illuminate\Console\Command;
use JumpLink\Vouchers\Models\Voucher;

/**
 * Asserts (or fixes) the balance invariant for every voucher:
 *   balance_cents == initial_value_cents - SUM(redemptions.amount_cents)
 *
 *   php artisan jumplink:vouchers-verify          # report drift
 *   php artisan jumplink:vouchers-verify --fix    # recompute + persist
 */
class VerifyBalances extends Command
{
    protected $signature = 'jumplink:vouchers-verify {--fix : Recompute and persist drifted balances}';

    protected $description = 'Verify (or fix) the voucher balance ledger invariant.';

    public function handle()
    {
        $bad = 0;
        $fix = (bool) $this->option('fix');

        Voucher::with('redemptions')->chunk(100, function ($vouchers) use (&$bad, $fix) {
            foreach ($vouchers as $voucher) {
                $expected = $voucher->ledgerBalance();
                if ((int) $voucher->balance_cents !== $expected) {
                    $bad++;
                    $this->warn(sprintf(
                        'Voucher %s: cached %d != ledger %d',
                        $voucher->code,
                        (int) $voucher->balance_cents,
                        $expected
                    ));
                    if ($fix) {
                        $voucher->recomputeBalance();
                        $this->info('  -> fixed');
                    }
                }
            }
        });

        if ($bad === 0) {
            $this->info('All voucher balances are consistent.');
            return 0;
        }

        $this->error($bad . ' voucher(s) inconsistent' . ($fix ? ' (fixed).' : '. Re-run with --fix.'));
        return 1;
    }
}
