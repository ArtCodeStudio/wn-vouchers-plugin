<?php namespace JumpLink\Vouchers\Classes;

use Log;
use Mail;
use URL;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;

/**
 * Sends the purchase emails — buyer confirmation (with the PDF attached and a
 * signed download link for digital delivery) plus an optional staff
 * notification. Mirrors JumpLink.Events BookingService::sendMails.
 *
 * A mail failure is logged but never bubbles up: an already-issued voucher must
 * not be rolled back because an SMTP server hiccupped.
 */
class NotificationService
{
    public static function sendPurchaseMails(VoucherOrder $order, ?Voucher $voucher): void
    {
        $brandName   = Settings::get('brand_name') ?: config('app.name');
        $senderEmail = Settings::get('sender_email');
        $senderName  = Settings::get('sender_name');

        $downloadUrl = self::downloadUrl($order, $voucher);

        $data = [
            'order'        => $order,
            'voucher'      => $voucher,
            'brand_name'   => $brandName,
            'download_url' => $downloadUrl,
        ];

        // Buyer confirmation.
        try {
            Mail::send('jumplink.vouchers::mail.purchase_confirmation', $data, function ($message) use ($order, $voucher, $senderEmail, $senderName) {
                $message->to($order->email, trim($order->firstname . ' ' . $order->lastname));
                if ($senderEmail) {
                    $message->from($senderEmail, $senderName ?: null);
                }
                if ($voucher && $order->delivery_type === 'digital') {
                    try {
                        $pdf = PdfService::render($voucher);
                        $message->attachData($pdf, 'gutschein-' . $voucher->code . '.pdf', ['mime' => 'application/pdf']);
                    } catch (\Throwable $e) {
                        Log::error('[vouchers] PDF attach failed: ' . $e->getMessage());
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('[vouchers] buyer mail failed: ' . $e->getMessage());
        }

        // Staff notification (mainly for physical fulfillment).
        $notifyEmail = Settings::get('notify_email');
        if ($notifyEmail) {
            try {
                Mail::send('jumplink.vouchers::mail.purchase_notification', $data, function ($message) use ($notifyEmail) {
                    $message->to($notifyEmail, Settings::get('notify_name') ?: null);
                });
            } catch (\Throwable $e) {
                Log::error('[vouchers] staff mail failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Email a voucher PDF directly (used by the on-site till sale, which has no
     * order). Attaches the PDF and includes a signed, time-limited download link.
     */
    public static function sendVoucherPdf(Voucher $voucher, string $email, ?string $recipientName = null): void
    {
        $brandName   = Settings::get('brand_name') ?: config('app.name');
        $senderEmail = Settings::get('sender_email');
        $senderName  = Settings::get('sender_name');

        $downloadUrl = null;
        try {
            $downloadUrl = URL::temporarySignedRoute('jumplink.vouchers.pdf', now()->addDays(30), ['voucher' => $voucher->id]);
        } catch (\Throwable $e) {
            // Link is optional; the PDF is attached anyway.
        }

        $data = [
            'voucher'        => $voucher,
            'brand_name'     => $brandName,
            'recipient_name' => $recipientName,
            'download_url'   => $downloadUrl,
        ];

        try {
            Mail::send('jumplink.vouchers::mail.voucher_delivery', $data, function ($message) use ($email, $recipientName, $voucher, $senderEmail, $senderName) {
                $message->to($email, $recipientName ?: null);
                if ($senderEmail) {
                    $message->from($senderEmail, $senderName ?: null);
                }
                try {
                    $pdf = PdfService::render($voucher);
                    $message->attachData($pdf, 'gutschein-' . $voucher->code . '.pdf', ['mime' => 'application/pdf']);
                } catch (\Throwable $e) {
                    Log::error('[vouchers] POS PDF attach failed: ' . $e->getMessage());
                }
            });
        } catch (\Throwable $e) {
            Log::error('[vouchers] POS delivery mail failed: ' . $e->getMessage());
        }
    }

    /** Tell the buyer their physical voucher card is on its way. */
    public static function sendShippingMail(VoucherOrder $order): void
    {
        $brandName   = Settings::get('brand_name') ?: config('app.name');
        $senderEmail = Settings::get('sender_email');
        $senderName  = Settings::get('sender_name');

        try {
            Mail::send('jumplink.vouchers::mail.shipping_notification', ['order' => $order, 'brand_name' => $brandName], function ($message) use ($order, $senderEmail, $senderName) {
                $message->to($order->email, trim($order->firstname . ' ' . $order->lastname));
                if ($senderEmail) {
                    $message->from($senderEmail, $senderName ?: null);
                }
            });
        } catch (\Throwable $e) {
            Log::error('[vouchers] shipping mail failed: ' . $e->getMessage());
        }
    }

    /** A signed, time-limited PDF download URL for a digital voucher. */
    protected static function downloadUrl(VoucherOrder $order, ?Voucher $voucher): ?string
    {
        if (!$voucher || $order->delivery_type !== 'digital') {
            return null;
        }
        try {
            return URL::temporarySignedRoute('jumplink.vouchers.pdf', now()->addDays(30), ['voucher' => $voucher->id]);
        } catch (\Throwable $e) {
            Log::error('[vouchers] could not sign download URL: ' . $e->getMessage());
            return null;
        }
    }
}
