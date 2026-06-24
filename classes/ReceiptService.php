<?php namespace JumpLink\Vouchers\Classes;

use View;
use Carbon\Carbon;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;

/**
 * Purchase receipt ("Kaufbeleg") for an online voucher order — a GoBD-suitable
 * document for the buyer's records and the shop's bookkeeping.
 *
 * For a multi-purpose voucher (the default) the sale is NOT a taxable supply
 * (§ 3 Abs. 15 UStG): the receipt deliberately shows NO VAT and carries the
 * statutory note that VAT arises only on redemption. This avoids the § 14c trap
 * (any VAT shown on the sale would then be owed). For a single-purpose voucher
 * (§ 3 Abs. 14 UStG) the sale is taxed when sold, so the receipt splits
 * net / VAT / gross at the configured rate.
 *
 * Renders views/pdf/receipt.blade.php via barryvdh/laravel-dompdf, like
 * PdfService. The seller identity (legal name, address, tax number) comes from
 * Settings — without a legal name no receipt is emitted (isConfigured()).
 *
 * NOTE: the exact wording, the Mehr-/Einzweckgutschein classification and the
 * VAT treatment of the shipping service fee must be confirmed with the shop's
 * tax advisor (see AGENTS.md). The defaults follow the common multi-purpose
 * handling.
 */
class ReceiptService
{
    /** A receipt can only be issued once a seller legal name is configured. */
    public static function isConfigured(): bool
    {
        return trim((string) Settings::get('seller_legal_name', '')) !== '';
    }

    /**
     * Structured receipt model for an order — pure data (cents + translation
     * keys), so it is unit-testable without rendering a PDF. The Blade template
     * formats it for print.
     */
    public static function buildModel(VoucherOrder $order): array
    {
        $faceCents  = (int) $order->face_value_cents;
        $feeCents   = (int) $order->service_fee_cents;
        $totalCents = (int) ($order->total_cents ?: ($faceCents + $feeCents));
        $isSingle   = $order->vat_mode === 'single_purpose';

        $lines = [[
            'label' => trans($isSingle
                ? 'jumplink.vouchers::lang.receipt.line_voucher_single'
                : 'jumplink.vouchers::lang.receipt.line_voucher_multi'),
            'cents' => $faceCents,
        ]];
        if ($feeCents > 0) {
            $lines[] = [
                'label' => trans('jumplink.vouchers::lang.receipt.line_service_fee'),
                'cents' => $feeCents,
            ];
        }

        // Single-purpose voucher: VAT is due at sale, so split the gross total
        // into net + VAT for the receipt.
        $vat = null;
        if ($isSingle) {
            // A stored 0 % is a valid rate — only fall back to 19 % when unset.
            $rate = $order->vat_rate !== null ? (float) $order->vat_rate : 19.0;
            $net  = (int) round($totalCents / (1 + $rate / 100));
            $vat  = [
                'rate'        => $rate,
                'net_cents'   => $net,
                'vat_cents'   => $totalCents - $net,
                'gross_cents' => $totalCents,
            ];
        }

        $address = null;
        if ($order->street || $order->zip || $order->city) {
            $address = trim(($order->street ?? '') . "\n" . trim(($order->zip ?? '') . ' ' . ($order->city ?? '')));
        }

        return [
            'number'      => $order->receipt_number,
            'date'        => $order->paid_at ?: $order->created_at ?: Carbon::now(),
            'seller'      => self::seller(),
            'buyer'       => [
                'name'    => trim(($order->firstname ?? '') . ' ' . ($order->lastname ?? '')),
                'email'   => $order->email,
                'address' => $address,
            ],
            'lines'       => $lines,
            'total_cents' => $totalCents,
            'vat_mode'    => $isSingle ? 'single_purpose' : 'multi_purpose',
            'vat'         => $vat,
            'note_key'    => $isSingle
                ? 'jumplink.vouchers::lang.receipt.note_single_purpose'
                : 'jumplink.vouchers::lang.receipt.note_multi_purpose',
            'payment'     => [
                'method_key' => $order->isBankTransfer()
                    ? 'jumplink.vouchers::lang.receipt.payment_banktransfer'
                    : 'jumplink.vouchers::lang.receipt.payment_online',
                // Bank transfer is matched by the reference (= receipt number);
                // online by the Mollie payment id when present.
                'reference'  => $order->isBankTransfer()
                    ? $order->receipt_number
                    : ($order->payment_id ?: $order->receipt_number),
            ],
            'extra_note'  => Settings::get('receipt_note') ?: null,
        ];
    }

    /** Render the receipt PDF and return the raw bytes (starts with "%PDF"). */
    public static function render(VoucherOrder $order): string
    {
        $settings = Settings::instance();

        $html = View::file(dirname(__DIR__) . '/views/pdf/receipt.blade.php', [
            'r'          => self::buildModel($order),
            'accent'     => $settings->pdf_accent_color ?: '#1a3a5a',
            'logo'       => PdfService::imageDataUri($settings->pdf_logo),
            'brand_name' => $settings->brand_name,
            // Cents -> "50,00 €" formatter for the template.
            'euro'       => fn ($cents) => VoucherOrder::formatEuro($cents),
        ])->render();

        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    /** Seller identity for the receipt header (legal name, address, tax number). */
    protected static function seller(): array
    {
        return [
            'name'       => Settings::get('seller_legal_name'),
            'address'    => Settings::get('seller_address'),
            'tax_number' => Settings::get('seller_tax_number'),
        ];
    }
}
