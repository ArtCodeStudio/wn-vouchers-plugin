<?php namespace JumpLink\Vouchers\Models;

use Model;

/**
 * Plugin settings (start number, fee, value bounds, VAT mode, Mollie mode,
 * sender/notify emails, PDF branding). Secrets (MOLLIE_API_KEY,
 * VOUCHER_TOKEN_SECRET) are NOT stored here — they are read from env() so they
 * never land in a CMS settings export.
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'jumplink_vouchers_settings';

    public $settingsFields = 'fields.yaml';

    public function initSettingsData()
    {
        // Numbering: auto numbers start here; must stay above the binder's
        // hand-written range so they can never collide.
        $this->voucher_start_number = 100000;

        // Money (cents).
        $this->service_fee_cents = 250;   // 2,50 € postal service fee (physical)
        $this->min_value_cents   = 1000;  // 10,00 €
        $this->max_value_cents   = 50000; // 500,00 €
        $this->denominations     = [
            ['value_cents' => 2500],
            ['value_cents' => 5000],
            ['value_cents' => 10000],
        ];

        // Validity (German gift-voucher norm ~3 years).
        $this->default_validity_months = 36;

        // VAT: Mehrzweckgutschein by default (no VAT at sale; due on redemption).
        $this->vat_mode = 'multi_purpose';
        $this->vat_rate = 19.0;           // used only in single_purpose mode
        $this->vat_rates = [
            ['rate' => 7.0],
            ['rate' => 19.0],
        ];

        // Payment.
        $this->mollie_mode = 'test';

        // Mail (mirrors JumpLink.Events).
        $this->notify_name = null;
        $this->notify_email = null;
        $this->sender_name = null;
        $this->sender_email = null;
        $this->send_customer_copy = true;

        // PDF branding.
        $this->pdf_accent_color = '#1a3a5a';
        $this->pdf_footer_text = null;
    }
}
