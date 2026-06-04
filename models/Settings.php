<?php namespace JumpLink\Vouchers\Models;

use Model;

/**
 * Plugin settings (start number, fee, value bounds, VAT mode, Mollie mode,
 * sender/notify emails, voucher design). Secrets (MOLLIE_API_KEY,
 * VOUCHER_TOKEN_SECRET) are NOT stored here — they are read from env() so they
 * never land in a CMS settings export.
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'jumplink_vouchers_settings';

    public $settingsFields = 'fields.yaml';

    /** Voucher design: an optional logo and a full-page "Briefpapier" background. */
    public $attachOne = [
        'pdf_logo'       => \System\Models\File::class,
        'pdf_background' => \System\Models\File::class,
    ];

    /** Translated VAT-mode labels for the settings dropdown. */
    public function getVatModeOptions()
    {
        return [
            'multi_purpose'  => trans('jumplink.vouchers::lang.vat_mode_option.multi_purpose'),
            'single_purpose' => trans('jumplink.vouchers::lang.vat_mode_option.single_purpose'),
        ];
    }

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

        // Validity: 3 years by default, rounded to year-end — this matches the
        // German statutory limitation (§§195/199 BGB), so printing it is accurate
        // and transparent. Set 0 to print no expiry at all.
        $this->default_validity_years = 3;

        // VAT: Mehrzweckgutschein by default (no VAT at sale; due on redemption).
        $this->vat_mode = 'multi_purpose';
        $this->vat_rate = 19.0;           // used only in single_purpose mode
        $this->vat_rates = [
            ['rate' => 7.0],
            ['rate' => 19.0],
        ];

        // Payment.
        $this->mollie_mode = 'test';

        // Till: the CMS page hosting the VoucherPos component (the QR scan
        // redirects here so a phone-camera scan lands on the staff till page).
        $this->pos_page_url = '/kasse/gutschein';

        // Mail (mirrors JumpLink.Events).
        $this->notify_name = null;
        $this->notify_email = null;
        $this->sender_name = null;
        $this->sender_email = null;
        $this->send_customer_copy = true;

        // Branding (used on the voucher and in emails).
        $this->brand_name = null;
        $this->pdf_accent_color = '#1a3a5a';
        $this->pdf_footer_text = null;
    }
}
