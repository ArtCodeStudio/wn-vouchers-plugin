<?php namespace JumpLink\Vouchers\Classes;

use Log;
use Mail;
use URL;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;

/**
 * Sends the purchase emails — buyer confirmation (with the voucher image
 * attached and a signed download link for digital delivery) plus an optional
 * staff notification. Mirrors JumpLink.Events BookingService::sendMails.
 *
 * A mail failure is logged but never bubbles up: an already-issued voucher must
 * not be rolled back because an SMTP server hiccupped.
 */
class NotificationService
{
    public static function sendPurchaseMails(VoucherOrder $order, ?Voucher $voucher): void
    {
        $brandName   = Settings::get('brand_name') ?: config('app.name');
        $downloadUrl = self::downloadUrl($order, $voucher);

        $data = [
            'order'        => $order,
            'voucher'      => $voucher,
            'brand_name'   => $brandName,
            'download_url' => $downloadUrl,
            // Buyer free-text, neutralised for the Markdown staff notification.
            'safe'         => self::safeBuyer($order),
        ];

        // Purchase receipt (Beleg): rendered once here — but only when it will be
        // used (the PDF is ~MB-sized) — then attached to the buyer confirmation
        // and/or copied to the bookkeeping inbox. Best-effort: a receipt failure
        // must never roll back an issued voucher.
        $buyerWantsReceipt = (bool) Settings::get('send_buyer_confirmation', true)
            && (bool) Settings::get('send_buyer_receipt', true);
        $accountingEmail   = trim((string) Settings::get('accounting_copy_email', ''));
        $receiptName       = 'beleg-' . $order->receipt_number . '.pdf';

        $receiptPdf = null;
        if (($buyerWantsReceipt || $accountingEmail !== '') && ReceiptService::isConfigured()) {
            try {
                $receiptPdf = ReceiptService::render($order);
            } catch (\Throwable $e) {
                Log::error('[vouchers] receipt render failed: ' . $e->getMessage());
            }
        }

        // Buyer confirmation (toggleable). Carries the voucher image (digital)
        // and the purchase receipt PDF (when enabled + a seller identity is set).
        if ((bool) Settings::get('send_buyer_confirmation', true)) {
            $attachReceipt = $receiptPdf !== null && $buyerWantsReceipt;
            try {
                Mail::send('jumplink.vouchers::mail.purchase_confirmation', $data, function ($message) use ($order, $voucher, $receiptPdf, $receiptName, $attachReceipt) {
                    $message->to($order->email, trim($order->firstname . ' ' . $order->lastname));
                    self::applySender($message);
                    if ($voucher && $order->delivery_type === 'digital') {
                        self::attachVoucher($message, $voucher);
                    }
                    if ($attachReceipt) {
                        $message->attachData($receiptPdf, $receiptName, ['mime' => 'application/pdf']);
                    }
                });
            } catch (\Throwable $e) {
                Log::error('[vouchers] buyer mail failed: ' . $e->getMessage());
            }
        }

        // Bookkeeping copy: a neutral mail with just the receipt PDF, for a DATEV
        // Belegtransfer inbox / the tax advisor / a Paperless mailbox.
        if ($receiptPdf !== null && $accountingEmail !== '') {
            self::sendAccountingCopy($order, $accountingEmail, $receiptPdf, $receiptName);
        }

        // Team notifications need a configured recipient.
        $notifyEmail = Settings::get('notify_email');
        if (!$notifyEmail) {
            return;
        }
        $notifyName = Settings::get('notify_name') ?: null;

        // Team: a new paid purchase (toggleable).
        if ((bool) Settings::get('notify_new_order', true)) {
            try {
                Mail::send('jumplink.vouchers::mail.purchase_notification', $data, function ($message) use ($notifyEmail, $notifyName) {
                    $message->to($notifyEmail, $notifyName);
                });
            } catch (\Throwable $e) {
                Log::error('[vouchers] staff new-order mail failed: ' . $e->getMessage());
            }
        }

        // Team: a physical card to prepare & post (toggleable). Carries the
        // voucher number/code + the delivery address so staff can fulfil it.
        if ($voucher && $order->delivery_type === 'physical' && (bool) Settings::get('notify_fulfillment', true)) {
            try {
                Mail::send('jumplink.vouchers::mail.fulfillment_notification', $data, function ($message) use ($notifyEmail, $notifyName) {
                    $message->to($notifyEmail, $notifyName);
                });
            } catch (\Throwable $e) {
                Log::error('[vouchers] staff fulfillment mail failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Email a voucher image directly (used by the on-site till sale, which has no
     * order). Attaches the image (PDF fallback) + a signed, time-limited link.
     */
    public static function sendVoucherImage(Voucher $voucher, string $email, ?string $recipientName = null): void
    {
        $brandName   = Settings::get('brand_name') ?: config('app.name');
        $downloadUrl = null;
        try {
            $downloadUrl = URL::temporarySignedRoute('jumplink.vouchers.image', now()->addDays(7), ['voucher' => $voucher->id]);
        } catch (\Throwable $e) {
            // Link is optional; the image is attached anyway.
        }

        $data = [
            'voucher'        => $voucher,
            'brand_name'     => $brandName,
            'recipient_name' => $recipientName,
            'download_url'   => $downloadUrl,
        ];

        try {
            Mail::send('jumplink.vouchers::mail.voucher_delivery', $data, function ($message) use ($email, $recipientName, $voucher) {
                $message->to($email, $recipientName ?: null);
                self::applySender($message);
                self::attachVoucher($message, $voucher);
            });
        } catch (\Throwable $e) {
            Log::error('[vouchers] POS delivery mail failed: ' . $e->getMessage());
        }
    }

    /** Tell the buyer their physical voucher card is on its way (toggleable). */
    public static function sendShippingMail(VoucherOrder $order): void
    {
        if (!(bool) Settings::get('send_buyer_shipping', true)) {
            return;
        }

        $brandName = Settings::get('brand_name') ?: config('app.name');

        try {
            Mail::send('jumplink.vouchers::mail.shipping_notification', ['order' => $order, 'brand_name' => $brandName], function ($message) use ($order) {
                $message->to($order->email, trim($order->firstname . ' ' . $order->lastname));
                self::applySender($message);
            });
        } catch (\Throwable $e) {
            Log::error('[vouchers] shipping mail failed: ' . $e->getMessage());
        }
    }

    /**
     * Bank transfer (Vorkasse): email the buyer the bank details + payment
     * reference so they can transfer, and notify staff of the new pending order so
     * they can watch for the incoming payment and then confirm it in the backend.
     * No voucher is issued here — that happens on confirmation.
     */
    public static function sendBankTransferMails(VoucherOrder $order): void
    {
        $brandName = Settings::get('brand_name') ?: config('app.name');

        $data = [
            'order'      => $order,
            'brand_name' => $brandName,
            'bank'       => Settings::bankTransferDetails(),
            'amount'     => VoucherOrder::formatEuro($order->total_cents),
            'reference'  => $order->transfer_reference,
            'safe'       => self::safeBuyer($order),
        ];

        // Buyer: how to pay (toggleable).
        if ((bool) Settings::get('send_buyer_bank_transfer', true)) {
            try {
                Mail::send('jumplink.vouchers::mail.bank_transfer_instructions', $data, function ($message) use ($order) {
                    $message->to($order->email, trim($order->firstname . ' ' . $order->lastname));
                    self::applySender($message);
                });
            } catch (\Throwable $e) {
                Log::error('[vouchers] bank-transfer buyer mail failed: ' . $e->getMessage());
            }
        }

        // Staff: a new order is awaiting payment (toggleable).
        $notifyEmail = Settings::get('notify_email');
        if ($notifyEmail && (bool) Settings::get('notify_bank_transfer', true)) {
            try {
                Mail::send('jumplink.vouchers::mail.bank_transfer_notification', $data, function ($message) use ($notifyEmail) {
                    $message->to($notifyEmail, Settings::get('notify_name') ?: null);
                });
            } catch (\Throwable $e) {
                Log::error('[vouchers] bank-transfer staff mail failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Send the purchase receipt to the bookkeeping inbox (DATEV Belegtransfer /
     * tax advisor / Paperless) as a neutral mail with the receipt PDF attached.
     * Best-effort; a failure is logged, never thrown.
     */
    protected static function sendAccountingCopy(VoucherOrder $order, string $email, string $receiptPdf, string $receiptName): void
    {
        $brandName = Settings::get('brand_name') ?: config('app.name');

        $data = [
            'order'      => $order,
            'brand_name' => $brandName,
            'number'     => $order->receipt_number,
            'amount'     => VoucherOrder::formatEuro($order->total_cents),
        ];

        try {
            Mail::send('jumplink.vouchers::mail.receipt_copy', $data, function ($message) use ($email, $receiptPdf, $receiptName) {
                $message->to($email);
                self::applySender($message);
                $message->attachData($receiptPdf, $receiptName, ['mime' => 'application/pdf']);
            });
        } catch (\Throwable $e) {
            Log::error('[vouchers] accounting copy mail failed: ' . $e->getMessage());
        }
    }

    /**
     * Neutralise one piece of buyer free-text for a Markdown email: strip HTML and
     * backslash-escape the Markdown punctuation that would otherwise turn the value
     * into a link / image / emphasis. This stops a buyer from injecting clickable
     * (phishing) links or markup into the staff notification inbox.
     */
    public static function mailText($value): string
    {
        $text = strip_tags((string) $value);
        // Escape Markdown punctuation so links/images/emphasis render literally.
        $text = preg_replace('/[\\\\`*_{}\[\]()#+!<>~|-]/', '\\\\$0', $text);
        // Break URL autolinking (scheme://…) so no clickable link can be injected.
        // `\:` renders as a plain colon, so the text stays readable.
        return preg_replace('~(?<=\w):(?=//)~', '\\\\:', $text);
    }

    /** The buyer-controlled fields, each sanitised for safe Markdown rendering. */
    protected static function safeBuyer(VoucherOrder $order): array
    {
        $safe = [];
        foreach (['firstname', 'lastname', 'email', 'phone', 'street', 'zip', 'city', 'recipient_name', 'message'] as $field) {
            $safe[$field] = self::mailText($order->$field);
        }
        return $safe;
    }

    /** Apply the configured sender (Settings) to an outgoing message, if set. */
    protected static function applySender($message): void
    {
        $senderEmail = Settings::get('sender_email');
        if ($senderEmail) {
            $message->from($senderEmail, Settings::get('sender_name') ?: null);
        }
    }

    /** Attach the voucher to a mail — as a JPEG image where possible, else PDF. */
    protected static function attachVoucher($message, Voucher $voucher): void
    {
        try {
            if (ImageService::isAvailable()) {
                $message->attachData(ImageService::render($voucher), 'gutschein-' . $voucher->code . '.jpg', ['mime' => 'image/jpeg']);
            } else {
                $message->attachData(PdfService::render($voucher), 'gutschein-' . $voucher->code . '.pdf', ['mime' => 'application/pdf']);
            }
        } catch (\Throwable $e) {
            Log::error('[vouchers] voucher attach failed: ' . $e->getMessage());
        }
    }

    /** A signed, time-limited download URL for a digital voucher image. */
    protected static function downloadUrl(VoucherOrder $order, ?Voucher $voucher): ?string
    {
        if (!$voucher || $order->delivery_type !== 'digital') {
            return null;
        }
        try {
            return URL::temporarySignedRoute('jumplink.vouchers.image', now()->addDays(7), ['voucher' => $voucher->id]);
        } catch (\Throwable $e) {
            Log::error('[vouchers] could not sign download URL: ' . $e->getMessage());
            return null;
        }
    }
}
