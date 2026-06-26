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

    /** Translated payment-mode labels for the settings dropdown. */
    public function getPaymentModeOptions()
    {
        return [
            'both'         => trans('jumplink.vouchers::lang.payment_mode_option.both'),
            'mollie'       => trans('jumplink.vouchers::lang.payment_mode_option.mollie'),
            'banktransfer' => trans('jumplink.vouchers::lang.payment_mode_option.banktransfer'),
        ];
    }

    /** Bank account details for the "pay by transfer" (Vorkasse) instructions. */
    public static function bankTransferDetails(): array
    {
        return [
            'holder' => static::get('bank_account_holder'),
            'iban'   => static::get('bank_iban'),
            'bic'    => static::get('bank_bic'),
            'bank'   => static::get('bank_name'),
            'note'   => static::get('bank_transfer_note'),
        ];
    }

    /**
     * Quick-pick amounts as bare cents, e.g. [2500, 5000, 10000] — a label-less
     * convenience over denominationList() for callers that only need the values.
     * (The buy form and till themselves use denominationList() to show labels.)
     */
    public static function denominationCents(): array
    {
        return array_map(fn ($d) => $d['cents'], static::denominationList());
    }

    /**
     * Quick-pick amounts with an optional label, e.g.
     * [['cents' => 2100, 'label' => 'Breakfast buffet'], ['cents' => 5000, 'label' => null]].
     * The label lets an operator name a preset amount (e.g. a fixed menu price) so
     * the buy form / till button reads more than just a number. Rows without a
     * positive amount are dropped; an empty label becomes null.
     */
    public static function denominationList(): array
    {
        return collect(static::get('denominations', []))
            ->map(fn ($row) => [
                'cents' => (int) ($row['value_cents'] ?? 0),
                'label' => trim((string) ($row['label'] ?? '')) ?: null,
            ])
            ->filter(fn ($row) => $row['cents'] > 0)
            ->values()
            ->all();
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

        // Validity: no printed expiry by default (0). Set a positive number of
        // years to print one, rounded to year-end to match the German statutory
        // limitation (§§195/199 BGB), which keeps a printed date accurate.
        $this->default_validity_years = 0;

        // VAT: Mehrzweckgutschein by default (no VAT at sale; due on redemption).
        $this->vat_mode = 'multi_purpose';
        $this->vat_rate = 19.0;           // used only in single_purpose mode

        // Which payment methods the buy form offers. 'both' = Mollie (online) +
        // bank transfer, buyer chooses; 'mollie' / 'banktransfer' force one. Mollie
        // is only ever offered when an API key is configured, so a fresh live site
        // with no key automatically shows bank transfer only — until the key is
        // set, when both appear. See PaymentService::availableMethods().
        $this->payment_mode = 'both';

        // Bank details for the "pay by transfer" (Vorkasse) instructions.
        $this->bank_account_holder = null;
        $this->bank_iban           = null;
        $this->bank_bic            = null;
        $this->bank_name           = null;
        $this->bank_transfer_note  = null;

        // Receipt (Beleg): the seller identity printed on the buyer's purchase
        // receipt, plus an optional bookkeeping copy. A receipt is only emitted
        // once a legal name is set (ReceiptService::isConfigured). For a
        // multi-purpose voucher the receipt is a GoBD Beleg, deliberately NOT a
        // VAT invoice — see ReceiptService.
        $this->seller_legal_name     = null;
        $this->seller_address        = null;
        $this->seller_tax_number     = null;
        $this->receipt_note          = null;
        $this->send_buyer_receipt    = true;  // attach the receipt PDF to the buyer confirmation
        $this->accounting_copy_email = null;  // BCC the receipt to DATEV Belegtransfer / tax advisor / Paperless

        // DATEV export (operator/StB-specific; no tie to any bank). The account
        // numbers come from the tax advisor — see docs/umsatzsteuerliche-behandlung.md.
        $this->datev_consultant_number        = null; // Beraternummer
        $this->datev_client_number            = null; // Mandantennummer
        $this->datev_account_length           = 4;    // Sachkontenlänge
        $this->datev_money_account            = null; // Geldkonto/Verrechnungskonto (Konto)
        $this->datev_voucher_liability_account = null; // Gutschein-Verbindlichkeiten (Gegenkonto)

        // Privacy / GDPR: the buyer IP is stored for abuse auditing, then nulled
        // by jumplink:vouchers-prune-ips on orders older than this many days
        // (0 disables pruning). The fiscal fields (amount, payment id) are kept.
        $this->ip_retention_days = 90;

        // GDPR: anonymise the buyer's personal data on orders older than this many
        // days (0 = disabled). Default off — the fiscal retention obligation
        // usually means keeping the record ~10 years, so the operator/DPO sets
        // this deliberately. On-demand erasure runs from the backend regardless.
        $this->personal_data_retention_days = 0;

        // Till: the CMS page hosting the VoucherPos component (the QR scan
        // redirects here so a phone-camera scan lands on the staff till page).
        $this->pos_page_url = '/kasse/gutschein';

        // Mail (mirrors JumpLink.Events).
        $this->notify_name = null;
        $this->notify_email = null;
        $this->sender_name = null;
        $this->sender_email = null;

        // Which emails to send (all on by default). Team mails additionally
        // require a notify_email to be set.
        $this->notify_new_order         = true;  // team: every paid purchase
        $this->notify_fulfillment       = true;  // team: a physical card to prepare & post
        $this->notify_bank_transfer     = true;  // team: a new transfer awaiting payment
        $this->send_buyer_confirmation  = true;  // buyer: voucher confirmation
        $this->send_buyer_bank_transfer = true;  // buyer: transfer instructions
        $this->send_buyer_shipping      = true;  // buyer: "card on its way"

        // Branding (used on the voucher and in emails).
        $this->brand_name = null;
        $this->pdf_accent_color = '#1a3a5a';
        $this->pdf_footer_text = null;
    }
}
