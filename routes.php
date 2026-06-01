<?php

/**
 * JSON API + webhook routes for the voucher system.
 *
 * Mirrors the JumpLink.Events route style (prefixed group + throttle). The
 * controllers are wired in M1; the group is declared here so the structure is
 * visible and the webhook URL is stable.
 *
 *   POST   api/jumplink/vouchers/purchase          create pending order + Mollie payment
 *   POST   api/jumplink/vouchers/webhook           Mollie callback (CSRF-exempt, re-fetch payment)
 *   GET    api/jumplink/vouchers/order/{id}/status return-page status poll
 *   GET    api/jumplink/vouchers/pdf/{voucher}     signed, time-limited PDF download
 *   GET    api/jumplink/vouchers/scan              signed-token lookup for the till
 *   POST   api/jumplink/vouchers/redeem            staff redemption (auth + idempotency)
 *
 * Implemented in M1 via JumpLink\Vouchers\Classes\Api.
 */

// Route::group(['prefix' => 'api/jumplink/vouchers'], function () {
//     Route::match(['post', 'options'], 'purchase', [\JumpLink\Vouchers\Classes\Api::class, 'purchase'])
//         ->middleware('throttle:20,1');
//     Route::post('webhook', [\JumpLink\Vouchers\Classes\Api::class, 'webhook']);
//     Route::get('order/{id}/status', [\JumpLink\Vouchers\Classes\Api::class, 'orderStatus']);
//     Route::get('pdf/{voucher}', [\JumpLink\Vouchers\Classes\Api::class, 'pdf'])->name('jumplink.vouchers.pdf');
//     Route::get('scan', [\JumpLink\Vouchers\Classes\Api::class, 'scan']);
//     Route::post('redeem', [\JumpLink\Vouchers\Classes\Api::class, 'redeem'])
//         ->middleware('throttle:60,1');
// });
