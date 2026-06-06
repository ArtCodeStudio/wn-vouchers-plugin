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
        $senderEmail = Settings::get('sender_email');
        $senderName  = Settings::get('sender_name');

        $downloadUrl = self::downloadUrl($order, $voucher);

        $data = [
            'order'        => $order,
            'voucher'      => $voucher,
            'brand_name'   => $brandName,
            'download_url' => $downloadUrl,
            // Buyer free-text, neutralised for the Markdown staff notification.
            'safe'         => self::safeBuyer($order),
        ];

        // Buyer confirmation.
        try {
            Mail::send('jumplink.vouchers::mail.purchase_confirmation', $data, function ($message) use ($order, $voucher, $senderEmail, $senderName) {
                $message->to($order->email, trim($order->firstname . ' ' . $order->lastname));
                if ($senderEmail) {
                    $message->from($senderEmail, $senderName ?: null);
                }
                if ($voucher && $order->delivery_type === 'digital') {
                    self::attachVoucher($message, $voucher);
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
     * Email a voucher image directly (used by the on-site till sale, which has no
     * order). Attaches the image (PDF fallback) + a signed, time-limited link.
     */
    public static function sendVoucherImage(Voucher $voucher, string $email, ?string $recipientName = null): void
    {
        $brandName   = Settings::get('brand_name') ?: config('app.name');
        $senderEmail = Settings::get('sender_email');
        $senderName  = Settings::get('sender_name');

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
            Mail::send('jumplink.vouchers::mail.voucher_delivery', $data, function ($message) use ($email, $recipientName, $voucher, $senderEmail, $senderName) {
                $message->to($email, $recipientName ?: null);
                if ($senderEmail) {
                    $message->from($senderEmail, $senderName ?: null);
                }
                self::attachVoucher($message, $voucher);
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

    /** Attach the voucher to a mail — as a PNG image where possible, else PDF. */
    protected static function attachVoucher($message, Voucher $voucher): void
    {
        try {
            if (ImageService::isAvailable()) {
                $message->attachData(ImageService::render($voucher), 'gutschein-' . $voucher->code . '.png', ['mime' => 'image/png']);
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
