# JumpLink.Vouchers

A WinterCMS plugin for **gift vouchers ("Gutscheine")** — built for the
*Mahlzeit am Meer* restaurant. Customers buy vouchers online, pay via **Mollie**,
and receive them **digitally** (PDF + QR by email) or **physically** (pre-printed
card by post). Staff manage vouchers and record **(partial) redemptions with a
running balance**; an iPad at the till can look up and redeem vouchers.

Deliberately standalone — not part of `JumpLink.Events` (which stays
payment-agnostic and is shared by multiple themes). It reuses the proven
JumpLink.Events conventions (Plugin registration, service classes, Settings,
mail templates, route style).

> Full design: [voucher-plugin-spec.md](../../docs/engagements/mahlzeit-am-meer-gutscheinsystem/voucher-plugin-spec.md)
> in the werkstatt repo.

## Status

**M0 — scaffolding (installable):** data model + migrations, backend UI
(Gutscheine / Bestellungen / Einlösungen + Settings), and the deterministic core
(numbering, ledger-safe redemption, code + signed QR token) are implemented and
tested. Mollie payment, PDF/QR rendering, the frontend purchase/return components
and the tablet POS page are stubbed with clear TODOs and land in **M1+**.

## Architecture

- **Money in integer cents.** The redemption **ledger** (`redemptions`) is the
  source of truth; `vouchers.balance_cents` is a row-locked cache.
- **Multi-purpose voucher (Mehrzweckgutschein)** by default: no VAT at sale; the
  VAT rate/split is captured at redemption (`redemptions.vat_breakdown`).
  Configurable in Settings.
- **Numbering:** `VoucherNumberService::allocate()` — atomic (`lockForUpdate`),
  starting at a configurable floor; manual binder numbers stay in a disjoint low
  range.
- **QR security:** the QR encodes a **signed token** (`VoucherCode::buildToken`,
  HMAC over the voucher id keyed by a per-voucher secret + an app pepper from
  `env('VOUCHER_TOKEN_SECRET')`), never the bare code.

## Layout

```
Plugin.php            registration (nav, permissions, settings, mail, console)
routes.php            JSON API + webhook group (wired in M1)
updates/              3 migrations + version.yaml
models/               VoucherOrder, Voucher, Redemption, Settings (+ yaml)
controllers/          Vouchers, Orders, Redemptions
classes/              VoucherNumberService, RedemptionService, VoucherCode (core);
                      PurchaseService, PaymentService, PdfService, QrService
components/           VoucherPurchase, VoucherReturn, VoucherPos (M1)
console/              VerifyBalances (jumplink:vouchers-verify)
views/mail|pdf/       templates
tests/unit/           ledger + code/token tests
```

## Install (development)

The plugin is bind-mounted into a local WinterCMS install — see the werkstatt
repo's [local development guide](../../docs/services/wintercms/local-development.md).

```bash
php artisan winter:up                      # runs the 3 migrations
php artisan jumplink:vouchers-verify        # asserts the balance invariant
php artisan jumplink:vouchers-verify --fix  # recompute drifted balances
```

### Runtime dependencies (added at the app level in M1)

```
mollie/mollie-api-php      # payment
barryvdh/laravel-dompdf    # voucher PDF
endroid/qr-code            # QR
```

Secrets live only in `.env` (never committed):

```
MOLLIE_API_KEY=test_…      # or live_…
VOUCHER_TOKEN_SECRET=…     # HMAC pepper for QR tokens
```

## Tests

```bash
# from the WinterCMS app root
php artisan winter:test -p JumpLink.Vouchers
```

Covered: partial redemption, over-redemption rejection, full-redemption status,
idempotency, the balance invariant, and code/token validity + tamper rejection.

## License

MIT © JumpLink – Art+Code Studio
