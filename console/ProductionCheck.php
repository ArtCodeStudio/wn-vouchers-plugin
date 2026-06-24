<?php namespace JumpLink\Vouchers\Console;

use Illuminate\Console\Command;
use JumpLink\Vouchers\Classes\ImageService;
use JumpLink\Vouchers\Classes\PaymentService;
use JumpLink\Vouchers\Classes\ReceiptService;
use JumpLink\Vouchers\Models\Settings;

/**
 * Pre-go-live checklist. Verifies the security-critical deployment config so a
 * misconfiguration (empty token pepper, test Mollie key, debug mode) is caught
 * loudly before the plugin serves real customers, instead of failing silently.
 *
 *   php artisan jumplink:vouchers-production-check
 *
 * Exit code is non-zero if any blocking check fails (usable in a deploy gate).
 */
class ProductionCheck extends Command
{
    protected $signature = 'jumplink:vouchers-production-check';

    protected $description = 'Verify the voucher plugin is safely configured for production (secret pepper, live Mollie key, debug off).';

    public function handle()
    {
        $fail = 0;

        $pepper = (string) env('VOUCHER_TOKEN_SECRET', '');
        if ($pepper === '') {
            $this->error('[FAIL] VOUCHER_TOKEN_SECRET is not set — QR redemption tokens would run unpeppered.');
            $fail++;
        } elseif (strlen($pepper) < 16) {
            $this->warn('[WARN] VOUCHER_TOKEN_SECRET is short (<16 chars); use a long random value.');
        } else {
            $this->info('[ OK ] VOUCHER_TOKEN_SECRET is set.');
        }

        // Mollie is only required when online payment is actually offered. A
        // bank-transfer-only site (payment_mode = banktransfer, or no key yet) is a
        // valid go-live state, so a missing/test key is fine there.
        $mollieOffered = PaymentService::isMethodAvailable(PaymentService::METHOD_MOLLIE);
        $mollie = (string) env('MOLLIE_API_KEY', '');
        if (!$mollieOffered) {
            $this->info('[ OK ] Online payment (Mollie) is not offered — bank transfer only.');
        } elseif ($mollie === '') {
            $this->error('[FAIL] MOLLIE_API_KEY is not set — online payments cannot be taken.');
            $fail++;
        } elseif (str_starts_with($mollie, 'test_')) {
            $this->error('[FAIL] MOLLIE_API_KEY is a TEST key — no real payments will be captured in production.');
            $fail++;
        } else {
            $this->info('[ OK ] MOLLIE_API_KEY is a live key.');
        }

        // Bank transfer offered -> the buyer needs an account to send money to.
        if (PaymentService::isMethodAvailable(PaymentService::METHOD_BANKTRANSFER)) {
            $iban   = trim((string) Settings::get('bank_iban', ''));
            $holder = trim((string) Settings::get('bank_account_holder', ''));
            if ($iban === '' || $holder === '') {
                $this->error('[FAIL] Bank transfer is offered but the bank details are incomplete — set the account holder + IBAN in Settings → Payment.');
                $fail++;
            } else {
                $this->info('[ OK ] Bank transfer details are set.');
            }
        }

        // Purchase receipts need a seller identity. Not a hard blocker (a site
        // may run without receipts), but warn loudly if they are switched on yet
        // cannot be issued.
        $wantsReceipt = (bool) Settings::get('send_buyer_receipt', true)
            || trim((string) Settings::get('accounting_copy_email', '')) !== '';
        if (ReceiptService::isConfigured()) {
            $this->info('[ OK ] Seller identity for purchase receipts is set.');
        } elseif ($wantsReceipt) {
            $this->warn('[WARN] Purchase receipts are enabled but the seller identity is incomplete — set the legal name (+ address, tax number) in Settings → Receipt, otherwise no receipt is issued.');
        }

        if ((bool) config('app.debug')) {
            $this->error('[FAIL] APP_DEBUG is on — turn it off in production (it leaks stack traces).');
            $fail++;
        } else {
            $this->info('[ OK ] APP_DEBUG is off.');
        }

        if (!str_starts_with((string) config('app.url'), 'https://')) {
            $this->warn('[WARN] APP_URL is not https:// — signed download links and the till must run over TLS.');
        } else {
            $this->info('[ OK ] APP_URL uses https.');
        }

        if (ImageService::isAvailable()) {
            $this->info('[ OK ] GD image rendering is available.');
        } else {
            $this->warn('[WARN] GD is unavailable — vouchers fall back to PDF rendering.');
        }

        if ($fail > 0) {
            $this->error("Production check FAILED with {$fail} blocking issue(s).");
            return 1;
        }
        $this->info('Production check passed.');
        return 0;
    }
}
