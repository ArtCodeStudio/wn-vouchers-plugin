<?php namespace JumpLink\Vouchers\Classes;

/**
 * Mollie payment wrapper (M1). Implemented behind this seam so the rest of the
 * plugin depends on our interface, not Mollie types (keeps a later Stripe/PayPal
 * swap or a shared commerce-core extraction cheap).
 *
 * Design:
 *   startPayment(order)  -> Mollie createPayment(amount=total, EUR,
 *                           redirectUrl=Return?order=ID, webhookUrl=.../webhook,
 *                           metadata={order_id}); store payment_id; return checkout URL.
 *   handleWebhook(id)    -> RE-FETCH the payment (never trust the POST body);
 *                           on `paid` and not yet issued: issue voucher(s) in a
 *                           transaction (VoucherNumberService::allocate), generate
 *                           PDF, queue mail; idempotent.
 *
 * Requires composer dep `mollie/mollie-api-php` and env MOLLIE_API_KEY
 * (test_… / live_…). To be added at the app level in M1.
 */
class PaymentService
{
    public static function apiKey(): string
    {
        return (string) env('MOLLIE_API_KEY', '');
    }

    public static function isConfigured(): bool
    {
        return self::apiKey() !== '';
    }

    // public static function startPayment(VoucherOrder $order): string { /* M1 */ }
    // public static function handleWebhook(string $paymentId): void     { /* M1 */ }
}
