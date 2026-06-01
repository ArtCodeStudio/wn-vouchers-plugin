<?php namespace JumpLink\Vouchers\Classes;

use Db;
use Carbon\Carbon;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\Redemption;

/**
 * Ledger-safe (partial) redemption. The redemption decision is made against the
 * ledger inside a row lock, never against the cached balance, so concurrent
 * redemptions (till + backend) cannot over-redeem. An optional idempotency key
 * makes a retried submit a no-op.
 *
 * Result: ['success'=>bool, 'status'=>int, 'error'?=>string,
 *          'redemption'?=>Redemption, 'balance_cents'?=>int, 'idempotent'?=>bool]
 */
class RedemptionService
{
    public static function redeem(int $voucherId, int $amountCents, array $opts = []): array
    {
        if ($amountCents <= 0) {
            return ['success' => false, 'status' => 422, 'error' => 'amount_must_be_positive'];
        }

        $idempotencyKey = $opts['idempotency_key'] ?? null;

        return Db::transaction(function () use ($voucherId, $amountCents, $opts, $idempotencyKey) {
            // Idempotency: a retried submit returns the original row.
            if ($idempotencyKey) {
                $existing = Redemption::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    $voucher = Voucher::find($existing->voucher_id);
                    return [
                        'success'       => true,
                        'status'        => 200,
                        'redemption'    => $existing,
                        'balance_cents' => $voucher ? (int) $voucher->balance_cents : null,
                        'idempotent'    => true,
                    ];
                }
            }

            $voucher = Voucher::lockForUpdate()->find($voucherId);
            if (!$voucher) {
                return ['success' => false, 'status' => 404, 'error' => 'voucher_not_found'];
            }
            if (in_array($voucher->status, ['void', 'expired'], true)) {
                return ['success' => false, 'status' => 409, 'error' => 'voucher_' . $voucher->status];
            }

            $balance = $voucher->ledgerBalance();
            if ($amountCents > $balance) {
                return ['success' => false, 'status' => 422, 'error' => 'insufficient_balance', 'balance_cents' => $balance];
            }

            $newBalance = $balance - $amountCents;

            $redemption = new Redemption;
            $redemption->voucher_id          = $voucher->id;
            $redemption->amount_cents         = $amountCents;
            $redemption->balance_after_cents  = $newBalance;
            $redemption->kind                 = $opts['kind'] ?? 'redeem';
            $redemption->vat_breakdown        = $opts['vat_breakdown'] ?? null;
            $redemption->note                 = $opts['note'] ?? null;
            $redemption->redeemed_by          = $opts['redeemed_by'] ?? null;
            $redemption->source               = $opts['source'] ?? 'backend';
            $redemption->idempotency_key      = $idempotencyKey;
            $redemption->created_at           = Carbon::now();
            $redemption->save();

            $voucher->balance_cents = $newBalance;
            $voucher->status        = $newBalance <= 0 ? 'redeemed' : 'active';
            $voucher->save();

            return ['success' => true, 'status' => 200, 'redemption' => $redemption, 'balance_cents' => $newBalance];
        });
    }
}
