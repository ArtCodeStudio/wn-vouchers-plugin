<?php namespace JumpLink\Vouchers\Classes;

use Log;
use JumpLink\Vouchers\Models\VoucherOrder;

/**
 * Mollie payment wrapper. Implemented behind this seam so the rest of the plugin
 * depends on our interface, not Mollie types (keeps a later Stripe/PayPal swap
 * or a shared commerce-core extraction cheap), and so tests can inject a fake
 * client via setClientResolver() and never touch the network.
 *
 * Flow:
 *   startPayment(order, returnUrl) -> Mollie createPayment(amount=total, EUR,
 *       redirectUrl=returnUrl, webhookUrl=.../webhook, metadata={order_id});
 *       store payment_id; return the checkout URL to redirect the buyer to.
 *   handleWebhook(id) -> RE-FETCH the payment by id (never trust the POST body);
 *       on `paid` and not yet issued, issue the voucher(s) in a transaction and
 *       send the confirmation mail exactly once; idempotent on retries.
 *
 * Requires composer dep `mollie/mollie-api-php` (v3) and env MOLLIE_API_KEY
 * (test_… / live_…) at the app level.
 */
class PaymentService
{
    /** Test seam: when set, client() returns this instead of a real client. */
    protected static $clientResolver = null;

    public static function setClientResolver(?callable $resolver): void
    {
        self::$clientResolver = $resolver;
    }

    public static function apiKey(): string
    {
        return (string) env('MOLLIE_API_KEY', '');
    }

    public static function isConfigured(): bool
    {
        return self::apiKey() !== '';
    }

    /** A configured Mollie client, or the injected test double. */
    public static function client()
    {
        if (self::$clientResolver) {
            return (self::$clientResolver)();
        }
        $client = new \Mollie\Api\MollieApiClient();
        $client->setApiKey(self::apiKey());
        return $client;
    }

    /**
     * Create a Mollie payment for the order and return the checkout URL the
     * buyer must be redirected to. Persists the payment id on the order.
     */
    public static function startPayment(VoucherOrder $order, string $returnUrl): string
    {
        $params = [
            'amount' => [
                'currency' => $order->currency ?: 'EUR',
                'value'    => self::formatAmount((int) $order->total_cents),
            ],
            'description' => 'Gutschein #' . $order->id,
            'redirectUrl' => $returnUrl,
            'metadata'    => ['order_id' => $order->id],
        ];

        // Mollie only accepts (and only calls) a publicly reachable webhook URL.
        // On a local dev host it is omitted; issue the voucher afterwards by
        // re-running the webhook logic: `php artisan jumplink:vouchers-check-payment`.
        $webhookUrl = url('/api/jumplink/vouchers/webhook');
        if (self::isPublicUrl($webhookUrl)) {
            $params['webhookUrl'] = $webhookUrl;
        }

        $payment = self::client()->payments->create($params);

        $order->provider       = 'mollie';
        $order->payment_id     = $payment->id;
        $order->payment_status = $payment->status;
        $order->save();

        return (string) $payment->getCheckoutUrl();
    }

    /**
     * Whether a URL is public enough for Mollie to call back (not localhost, a
     * private/reserved IP, or a non-routable dev TLD). Used to skip webhookUrl in
     * local dev, where Mollie would reject it — issue via the
     * jumplink:vouchers-check-payment command instead.
     */
    public static function isPublicUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost') {
            return false;
        }
        // Non-routable dev TLDs, and any private/reserved IP (the reserved range
        // already covers 127.0.0.1 / ::1 loopback).
        if (preg_match('/\.(local|localhost|test|example|invalid)$/', $host)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)
            && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        return true;
    }

    /**
     * Handle a Mollie webhook. Re-fetches the payment by id; on `paid` it issues
     * the voucher(s) (idempotent) and sends the confirmation mail exactly once.
     * Other terminal states update the order status. Unexpected exceptions are
     * left to bubble so the caller can answer 5xx and Mollie retries later.
     */
    public static function handleWebhook(string $paymentId): void
    {
        $payment = self::client()->payments->get($paymentId);

        $order = VoucherOrder::where('payment_id', $paymentId)->first();
        if (!$order && isset($payment->metadata->order_id)) {
            $order = VoucherOrder::find($payment->metadata->order_id);
        }
        if (!$order) {
            Log::warning('[vouchers] webhook: no order for payment ' . $paymentId);
            return;
        }

        if ($payment->isPaid()) {
            $result = IssuanceService::issueForOrder($order);
            if ($result['created']) {
                NotificationService::sendPurchaseMails($order->fresh(), $result['voucher']);
            }
            return;
        }

        $order->payment_status = $payment->status;
        if ($payment->isCanceled()) {
            $order->status = 'cancelled';
        } elseif ($payment->isExpired()) {
            $order->status = 'expired';
        } elseif ($payment->isFailed()) {
            $order->status = 'failed';
        }
        $order->save();
    }

    /** cents -> Mollie "12.50" amount string (dot decimal, two places). */
    public static function formatAmount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
