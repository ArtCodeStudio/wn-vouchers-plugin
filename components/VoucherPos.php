<?php namespace JumpLink\Vouchers\Components;

use Flash;
use BackendAuth;
use Cms\Classes\ComponentBase;
use JumpLink\Vouchers\Models\Voucher;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Classes\PosService;
use JumpLink\Vouchers\Classes\RedemptionService;

/**
 * VoucherPos – the staff till page (iPad). Gated by a backend session + the
 * jumplink.vouchers.redeem_vouchers permission. Looks up a voucher by typed code
 * or QR scan, shows the balance, records a ledger-safe (partial) redemption, and
 * can sell a voucher on the spot. Every handler re-checks authorization
 * server-side, so hiding the UI is not the only line of defence.
 */
class VoucherPos extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Gutschein-Kasse (Einlösung)',
            'description' => 'Tablet-Einlöseseite für das Personal: Lookup (Code/QR), Restguthaben, Teileinlösung, Vor-Ort-Verkauf.',
        ];
    }

    /** Whether the current visitor may use the till page. */
    public function authorized(): bool
    {
        $user = BackendAuth::getUser();
        return $user && (
            $user->hasAccess('jumplink.vouchers.redeem_vouchers') ||
            $user->hasAccess('jumplink.vouchers.manage_vouchers')
        );
    }

    /** Pre-filled query when arriving from a scanned QR (?t=…) or ?code=…. */
    public function initialQuery(): string
    {
        return (string) (input('t') ?: input('code') ?: '');
    }

    public function paymentMethods(): array
    {
        return (new Voucher)->getPaymentMethodOptions();
    }

    public function onLookup()
    {
        $this->ensureAuthorized();

        $voucher = PosService::resolveVoucher((string) post('q'));
        if (!$voucher) {
            return ['#posResult' => $this->renderPartial('@result', [
                'voucher' => null,
                'error'   => 'Kein Gutschein gefunden. Bitte Code prüfen.',
            ])];
        }

        return ['#posResult' => $this->renderResult($voucher)];
    }

    public function onRedeem()
    {
        $this->ensureAuthorized();

        $voucher = Voucher::find((int) post('voucher_id'));
        if (!$voucher) {
            throw new \ApplicationException('Gutschein nicht gefunden.');
        }

        $amountCents = PosService::toCents(post('amount'));
        if ($amountCents <= 0) {
            throw new \ApplicationException('Bitte einen gültigen Betrag eingeben.');
        }

        $user = BackendAuth::getUser();
        $result = RedemptionService::redeem($voucher->id, $amountCents, [
            'source'          => 'pos',
            'redeemed_by'     => $user ? $user->id : null,
            'idempotency_key' => post('nonce') ?: null, // guards against a double-tap
        ]);

        if (empty($result['success'])) {
            throw new \ApplicationException(PosService::redeemError($result['error'] ?? 'error', $result['balance_cents'] ?? null));
        }

        Flash::success('Eingelöst. Neues Restguthaben: ' . VoucherOrder::formatEuro($result['balance_cents']));

        return ['#posResult' => $this->renderResult($voucher->fresh())];
    }

    public function onSell()
    {
        $this->ensureAuthorized();

        $user = BackendAuth::getUser();
        $result = PosService::sell((array) post(), $user ? $user->id : null);
        if (empty($result['success'])) {
            throw new \ApplicationException($result['error'] ?? 'Verkauf fehlgeschlagen.');
        }

        Flash::success('Gutschein angelegt: ' . $result['voucher']->code);

        return ['#posSold' => $this->renderPartial('@sold', ['voucher' => $result['voucher']])];
    }

    protected function renderResult(Voucher $voucher): string
    {
        return $this->renderPartial('@result', [
            'voucher' => $voucher,
            'nonce'   => uniqid('pos', true),
        ]);
    }

    protected function ensureAuthorized(): void
    {
        if (!$this->authorized()) {
            throw new \ApplicationException('Keine Berechtigung. Bitte im Backend anmelden.');
        }
    }
}
