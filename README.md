# JumpLink.Vouchers

> A gift-voucher (“Gutschein”) system for [WinterCMS](https://wintercms.com).

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![WinterCMS](https://img.shields.io/badge/WinterCMS-1.2-blue.svg)](https://wintercms.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg)](https://www.php.net)

Sell gift vouchers online with validated data and real payments, deliver them
**digitally** (PDF + QR code by email) or **physically** (pre-printed card by
post), and let staff redeem them — including **partial redemptions with a
running balance** — from the backend or a tablet at the till.

Originally built to replace a brittle “request a voucher” contact form for a
seaside restaurant, but written as a self-contained, reusable plugin: money,
numbering, ledger, payment, PDF/QR and fulfillment are all generic.

> **Standalone by design.** This is *not* an extension of
> [`JumpLink.Events`](https://github.com/ArtCodeStudio/wn-events-plugin). Vouchers
> (monetary value, sequential numbering, a balance ledger, payment, PDF/QR,
> fulfillment) are a distinct domain. The proven Events conventions (plugin
> registration, service classes, settings, mail templates, route style) are
> *reused, not coupled*.

---

## Features

- 🛒 **Online purchase** with a free amount (“Wunschbetrag”) plus quick-pick
  buttons, bounded by configurable min/max.
- 💳 **Mollie payment** (Apple/Google Pay, cards, SEPA, PayPal via one
  integration). The webhook is the **sole** voucher-issuing authority — no
  voucher is created until the payment is confirmed server-side.
- 📄 **Digital delivery**: a branded PDF with a QR code, downloadable and emailed.
- 📬 **Physical delivery**: a pre-printed card by post, with a configurable
  service fee and a shipping notification for staff.
- 💶 **Partial redemption with a running balance**: a 50 € voucher spent in a
  30 € order leaves 20 €. Backed by an **append-only ledger**, so every
  redemption is auditable and the balance can always be recomputed.
- 🔢 **Configurable starting number** so you can continue an existing paper
  ledger; auto-issued numbers and hand-written ones stay in disjoint ranges and
  can never collide.
- 🧾 **Multi-purpose voucher (Mehrzweckgutschein) VAT model** by default: no VAT
  at sale, the VAT rate/split is captured at **redemption**.
- 🔐 **Signed QR tokens** (HMAC), not the bare code — a leaked voucher number
  can’t be forged into a redeem link.
- 🛠️ **Backend management** of orders, vouchers and redemptions, plus a settings
  page for numbering, fees, denominations, VAT mode and PDF branding.

## Status & roadmap

This plugin is built in milestones. **M0 is complete and installable.**

| Milestone | Scope | Status |
|-----------|-------|--------|
| **M0** | Data model + migrations, backend UI, deterministic core (numbering, ledger-safe redemption, code + signed QR token), test suite | ✅ Done |
| **M1** | Frontend purchase component, Mollie flow, PDF/QR rendering, confirmation email, backend partial redemption | 🛠️ In progress |
| **M2** | Physical fulfillment + service fee + shipping notification + manual numbering | ⏳ Planned |
| **M3** | Tablet POS page (backend-auth gated, QR camera scan, on-site sale) | ⏳ Planned |
| **M4** | Accounting reconciliation, retention/anonymization | ⏳ Planned |

In M0, Mollie payment, PDF/QR rendering and the frontend components are present
as clearly marked stubs; the deterministic core they build on is fully
implemented and tested.

## Requirements

- WinterCMS **1.2+** (Laravel 9 era), PHP **8.1+**
- A [Mollie](https://www.mollie.com) account (test key works for development)
- For PDF/QR (M1), three app-level Composer packages — see below.

## Installation

Until this is published on Packagist, install it as a plugin directory:

```bash
# from your WinterCMS project root
git clone https://github.com/ArtCodeStudio/wn-vouchers-plugin.git \
  plugins/jumplink/vouchers

php artisan winter:up    # runs the 3 migrations
```

### Runtime dependencies (M1)

Payment, PDF and QR rendering need three packages, installed at the **app**
level (not vendored in the plugin):

```bash
composer require mollie/mollie-api-php barryvdh/laravel-dompdf endroid/qr-code
```

## Configuration

Secrets live only in your `.env` (never in the database, settings, or git):

```dotenv
MOLLIE_API_KEY=test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx   # or live_…
VOUCHER_TOKEN_SECRET=change-me                         # HMAC pepper for QR tokens
```

Everything else is configured in the backend under **Settings → Vouchers**:
starting number, service fee, min/max value, denominations, default validity,
VAT mode, Mollie mode (test/live), sender/notification addresses and PDF
branding.

## How it works

### Money is integer cents

All amounts are stored as integer cents (`balance_cents`, `face_value_cents`, …)
to avoid floating-point rounding errors in balance arithmetic. Formatting to
euros happens only at the view layer.

### The ledger is the source of truth

Three tables back the plugin:

- **`jumplink_vouchers_voucher_orders`** — a purchase and its payment. Created
  as `pending`; it only issues vouchers once the payment is confirmed.
- **`jumplink_vouchers_vouchers`** — the voucher itself: code, number, initial
  value, a **cached** `balance_cents`, status, and a per-voucher token secret.
- **`jumplink_vouchers_redemptions`** — an **append-only ledger**. Every (partial)
  redemption, reversal or adjustment is one immutable row with `amount_cents`,
  a balance snapshot, a `vat_breakdown`, and an idempotency key.

The invariant `balance_cents == initial_value_cents − SUM(redemptions)` always
holds. `balance_cents` is just a row-locked cache; the ledger can always rebuild
it. Writes go through `RedemptionService::redeem()` inside a DB transaction with
`lockForUpdate()`, re-reading the ledger sum **inside** the lock and rejecting
over-redemption — safe against double-taps and the Christmas rush.

### Numbering can’t collide

`VoucherNumberService::allocate()` hands out auto numbers atomically
(`lockForUpdate` on the current max, `+1`) starting from a configurable floor
(e.g. `100000`). Hand-written paper-ledger numbers live in a disjoint low range
(`number_source = 'manual'`), so the two can never overlap — matching the mental
model of a shop continuing its existing binder.

### QR codes carry a signed token

The human-readable code (e.g. `MAM-100042-K`, with a mod-36 check character for
phone/till typos) is guessable and sequential, so it is **not** what the QR
encodes. The QR carries a signed token — an HMAC over the voucher id keyed by
the per-voucher secret plus an app pepper (`VOUCHER_TOKEN_SECRET`). A leaked
number can’t be forged into a redeem link, and the QR never debits anything by
itself; it only opens an **authenticated** lookup.

### VAT: multi-purpose voucher by default

For a multi-purpose voucher (Mehrzweckgutschein), VAT is not due at sale but at
**redemption**, at whichever rate applies to what was actually bought (e.g. a
reduced food rate vs. a standard rate for drinks/service). The plugin records
the rate/split per redemption in `vat_breakdown`; the sale receipt carries the
legal multi-purpose-voucher note. The mode is configurable (multi-purpose ↔
single-purpose). *Always confirm the treatment with your tax advisor.*

## Usage

### Components

| Component | Tag | Purpose |
|-----------|-----|---------|
| `VoucherPurchase` | `voucherPurchase` | Purchase form on the buy page (amount + buyer/recipient data, Mollie redirect). |
| `VoucherReturn` | `voucherReturn` | Landing page after payment — polls order status, offers the PDF download. |
| `VoucherPos` | `voucherPos` | Backend-auth-gated till page for on-site lookup, redemption and sale (M3). |

### Backend

A **Vouchers** main menu with **Vouchers / Orders / Redemptions** submenus
(orders carry a “paid, not yet fulfilled” counter), plus permissions
`jumplink.vouchers.manage_vouchers`, `…manage_orders` and `…redeem_vouchers`.

### Console

```bash
php artisan jumplink:vouchers-verify         # assert the balance invariant for every voucher
php artisan jumplink:vouchers-verify --fix   # recompute any drifted cached balances from the ledger
```

## Development

The plugin is developed against a local WinterCMS install with the plugin
bind-mounted (or cloned) into `plugins/jumplink/vouchers`:

```bash
composer create-project wintercms/winter my-winter
cd my-winter
git clone https://github.com/ArtCodeStudio/wn-vouchers-plugin.git \
  plugins/jumplink/vouchers
php artisan winter:up
```

Any container runtime works (Docker, Podman, Lando, …) as long as PHP 8.1+ with
`gd`, `pdo_mysql`, `intl`, `zip`, `bcmath` and `mbstring` is available.

### Tests

```bash
# from the WinterCMS app root
php artisan winter:test -p JumpLink.Vouchers
```

The suite covers partial redemption, over-redemption rejection, the
full-redemption status transition, idempotency, the balance invariant, and
code/token validity plus tamper rejection. Mollie is mocked at the
`PaymentService` seam, so the tests never touch the network.

## Security

- Honeypot + server-side validation on the purchase endpoint; rate-limiting on
  purchase and redemption.
- The Mollie webhook never trusts the request body — it re-fetches the payment
  by id and checks the order metadata.
- Signed, time-limited URLs for PDF downloads.
- Data minimization: digital vouchers need no postal address; the buyer IP is
  kept only briefly for abuse auditing, with a retention/anonymization path.
- Secrets stay in `.env`; nothing sensitive is committed.

## Contributing

Issues and pull requests are welcome. Please keep money in integer cents, treat
the redemption ledger as append-only, and add a test for any change to the
numbering, balance or token logic.

## License

[MIT](LICENSE) © JumpLink – Art+Code Studio
