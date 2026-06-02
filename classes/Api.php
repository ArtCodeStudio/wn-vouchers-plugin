<?php namespace JumpLink\Vouchers\Classes;

use Request;
use Response;
use JumpLink\Vouchers\Models\Voucher;

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
}
