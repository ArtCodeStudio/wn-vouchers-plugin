<?php

/**
 * HTTP routes for the voucher system.
 *
 * Registered outside the CMS `web` middleware group (no session/CSRF), so they
 * behave like an API — which is what the Mollie webhook needs.
 *
 *   POST  api/jumplink/vouchers/webhook        Mollie callback (re-fetches the payment)
 *   GET   api/jumplink/vouchers/pdf/{voucher}  signed, time-limited PDF download
 *
 * The purchase itself is handled by the VoucherPurchase component's AJAX
 * handler (CSRF-protected, inside the CMS). The till scan + redeem endpoints
 * land with the tablet POS page in M3.
 */

Route::group(['prefix' => 'api/jumplink/vouchers'], function () {
    Route::post('webhook', [\JumpLink\Vouchers\Classes\Api::class, 'webhook']);

    Route::get('pdf/{voucher}', [\JumpLink\Vouchers\Classes\Api::class, 'pdf'])
        ->name('jumplink.vouchers.pdf');

    // A scanned QR opens this; redirect to the staff till page with the token.
    Route::get('scan', [\JumpLink\Vouchers\Classes\Api::class, 'scan']);
});
