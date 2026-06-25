<?php

/**
 * HTTP routes for the voucher system.
 *
 * Registered outside the CMS `web` middleware group (no session/CSRF), so they
 * behave like an API — which is what the Mollie webhook needs. Every route is
 * rate-limited per IP (throttle) to blunt floods / enumeration / render abuse.
 *
 *   POST  api/jumplink/vouchers/webhook        Mollie callback (re-fetches the payment)
 *   POST  api/jumplink/vouchers/order-status   token-authorised return-page status poll
 *   GET   api/jumplink/vouchers/pdf/{voucher}   signed, time-limited PDF download
 *   GET   api/jumplink/vouchers/image/{voucher} signed, time-limited JPEG download
 *   GET   api/jumplink/vouchers/scan            QR target -> redirect to the till page
 *
 * The purchase itself is handled by the VoucherPurchase component's AJAX handler
 * (inside the CMS), which rate-limits per IP in onPurchase.
 */

Route::group(['prefix' => 'api/jumplink/vouchers'], function () {
    Route::post('webhook', [\JumpLink\Vouchers\Classes\Api::class, 'webhook'])
        ->middleware('throttle:60,1');

    // POST (not GET) so the per-order access token travels in the body, not the
    // query string — keeps it out of access logs, browser history and referrers.
    Route::post('order-status', [\JumpLink\Vouchers\Classes\Api::class, 'orderStatus'])
        ->middleware('throttle:60,1');

    // PDF/PNG render is CPU-heavy; signature-gated but throttled against abuse.
    Route::get('pdf/{voucher}', [\JumpLink\Vouchers\Classes\Api::class, 'pdf'])
        ->name('jumplink.vouchers.pdf')
        ->middleware('throttle:20,1');

    Route::get('image/{voucher}', [\JumpLink\Vouchers\Classes\Api::class, 'image'])
        ->name('jumplink.vouchers.image')
        ->middleware('throttle:20,1');

    // A scanned QR opens this; redirect to the staff till page with the token.
    Route::get('scan', [\JumpLink\Vouchers\Classes\Api::class, 'scan'])
        ->middleware('throttle:30,1');
});
