<?php namespace JumpLink\Vouchers\Classes;

use URL;
use Request;
use Response;
use Redirect;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;

/**
 * Public HTTP endpoints for the voucher system (wired in routes.php).
 *
 * These routes live outside the CMS `web` middleware group, so they behave like
 * an API: no session, no CSRF. The webhook re-fetches the payment server-side
 * (it never trusts its body); the PDF route is protected by a signed,
 * time-limited URL instead.
 */
class Api
{
    /**
     * Mollie server-to-server callback. Mollie POSTs only the payment id; we
     * re-fetch the payment and act on its real status. Unexpected exceptions
     * bubble to a 500 so Mollie retries the webhook later.
     */
    public function webhook()
    {
        $id = Request::input('id');
        if (!$id) {
            return Response::make('missing id', 400);
        }
        PaymentService::handleWebhook((string) $id);
        return Response::make('OK', 200);
    }

    /**
     * Return-page status poll (token-authorised, JSON). Lets the buyer's browser
     * swap to the issued state in place — no hard reload — while the webhook (or,
     * locally, jumplink:vouchers-check-payment) issues the voucher.
     */
    public function orderStatus()
    {
        $order = VoucherOrder::findForReturn((int) Request::input('order'), (string) Request::input('t'));
        if (!$order) {
            return Response::json(['issued' => false], 404);
        }

        $downloadUrl = null;
        if ($order->status === 'issued') {
            $voucher = $order->vouchers()->first();
            if ($voucher && $voucher->type === 'digital') {
                $downloadUrl = URL::temporarySignedRoute('jumplink.vouchers.pdf', now()->addDays(30), ['voucher' => $voucher->id]);
            }
        }

        return Response::json([
            'issued'      => $order->status === 'issued',
            'downloadUrl' => $downloadUrl,
        ]);
    }

    /** Signed, time-limited PDF download for the buyer's digital voucher. */
    public function pdf($voucherId)
    {
        if (!Request::hasValidSignature()) {
            return Response::make('invalid or expired link', 403);
        }
        $voucher = Voucher::find($voucherId);
        if (!$voucher) {
            return Response::make('not found', 404);
        }
        return Response::make(PdfService::render($voucher), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="gutschein-' . $voucher->code . '.pdf"',
        ]);
    }

    /**
     * Target of the QR code. Redirects the (staff) browser to the configured
     * till page with the token, where VoucherPos resolves and shows the voucher.
     * No voucher data is exposed here — the till page enforces the login.
     */
    public function scan()
    {
        $token = (string) Request::get('t');
        $base = (string) Settings::get('pos_page_url', '/kasse/gutschein');
        if ($token === '') {
            return Redirect::to($base);
        }
        $separator = strpos($base, '?') !== false ? '&' : '?';
        return Redirect::to($base . $separator . 't=' . urlencode($token));
    }
}
