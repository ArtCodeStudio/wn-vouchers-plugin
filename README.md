# JumpLink.Vouchers

> A gift-voucher (“Gutschein”) system for [WinterCMS](https://wintercms.com).

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![WinterCMS](https://img.shields.io/badge/WinterCMS-1.2-blue.svg)](https://wintercms.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg)](https://www.php.net)

Sell gift vouchers online with validated data and real payments, deliver them
**digitally** (image + QR code by email) or **physically** (pre-printed card by
post), and let staff redeem them — including **partial redemptions with a
running balance** — from the backend or a tablet at the till.

Originally built to replace a brittle “request a voucher” contact form for a
seaside restaurant, but written as a self-contained, reusable plugin: money,
numbering, ledger, payment, image/QR and fulfillment are all generic.

> **Standalone by design.** This is *not* an extension of
> [`JumpLink.Events`](https://github.com/ArtCodeStudio/wn-events-plugin). Vouchers
> (monetary value, sequential numbering, a balance ledger, payment, image/QR,
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
- 🏦 **Bank transfer (prepayment)** as an alternative or fallback: offer Mollie,
  bank transfer, or both (the buyer chooses). With no Mollie key configured the
  form automatically shows bank transfer only — so a shop can go live before
  Mollie onboarding is finished. Staff confirm the incoming payment in the
  backend, which issues the voucher (same path as the webhook — never before
  payment).
- 📧 **Configurable emails**: per-email toggles for buyer mails (confirmation,
  transfer instructions, shipping) and team notifications — including a dedicated
  **“prepare for shipping”** alert (voucher number + delivery address) for physical
  orders. Every subject carries the key facts (code, amount, reference, destination).
- 🖼️ **Digital delivery**: a branded image (PNG) with a QR code, downloadable and emailed.
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
- 🧾 **Purchase receipt (Kaufbeleg) as PDF** for every online order — no VAT shown
  for a multi-purpose voucher (with the § 3 Abs. 15 UStG note), with an optional
  automatic copy to a DATEV Belegtransfer / tax-advisor / Paperless inbox.
- 🔐 **Signed QR tokens** (HMAC), not the bare code — a leaked voucher number
  can’t be forged into a redeem link.
- 🛠️ **Backend management** of orders, vouchers and redemptions, plus a settings
  page for numbering, fees, denominations, VAT mode and PDF branding.

## Status & roadmap

This plugin is built in milestones. **The digital MVP (M0 + M1) is complete and
tested.**

| Milestone | Scope | Status |
|-----------|-------|--------|
| **M0** | Data model + migrations, backend UI, deterministic core (numbering, ledger-safe redemption, code + signed QR token), test suite | ✅ Done |
| **M1** | Purchase + return components, Mollie payment flow (webhook issuance), PDF/QR rendering, confirmation email, backend partial redemption | ✅ Done |
| **M2** | Physical fulfillment + service fee + shipping notification + manual numbering | ⏳ Planned |
| **M3** | Tablet POS page (backend-auth gated, QR camera scan, on-site sale) | ⏳ Planned |
| **M4** | Accounting reconciliation, retention/anonymization | ⏳ Planned |

The end-to-end digital flow works: a buyer picks an amount, pays via Mollie, the
webhook issues exactly one numbered voucher, the PDF (with signed QR) is rendered
and emailed, and staff can book ledger-safe (partial) redemptions from the
backend. The physical-card service fee + shipping and the tablet POS scan page
follow in M2/M3.

## Requirements

- WinterCMS **1.2+** (Laravel 9 era), PHP **8.1+**
- A [Mollie](https://www.mollie.com) account for online payments (test key works
  for development) — *optional*: the plugin can run bank-transfer-only without it
- Payment/PDF/QR rely on `mollie/mollie-api-php`, `barryvdh/laravel-dompdf` and
  `endroid/qr-code` — declared as dependencies of this plugin.

## Installation

Until this is published on Packagist, install it as a plugin directory and pull
its runtime dependencies into the app:

```bash
# from your WinterCMS project root
git clone https://github.com/ArtCodeStudio/wn-vouchers-plugin.git \
  plugins/jumplink/vouchers

# Install the plugin's third-party dependencies into the app (Winter does not
# run Laravel package auto-discovery, so the plugin registers dompdf itself).
composer require mollie/mollie-api-php barryvdh/laravel-dompdf endroid/qr-code

php artisan winter:up    # runs the 3 migrations
```

## Configuration

Secrets live only in your `.env` (never in the database, settings, or git):

```dotenv
MOLLIE_API_KEY=test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx   # or live_…
VOUCHER_TOKEN_SECRET=change-me                         # HMAC pepper for QR tokens
```

Everything else is configured in the backend under **Settings → Vouchers**:
starting number, service fee, min/max value, denominations, default validity,
VAT mode, **payment mode (Mollie / bank transfer / both) and the bank details
for transfers**, Mollie mode (test/live), sender/notification addresses and PDF
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
  a balance snapshot, and an idempotency key.

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
reduced food rate vs. a standard rate for drinks/service). The plugin stamps the
`vat_mode` on the order and voucher (so the sale is booked with no VAT) — but it
does **not** record the VAT split at redemption: when the voucher is redeemed it
is only the means of payment, and the VAT on the actual meal is recorded by the
restaurant's (TSE) cash register, which is the authoritative record. The mode is
configurable (multi-purpose ↔ single-purpose). *Always confirm the treatment
with your tax advisor.*

A plain-language summary of the VAT/bookkeeping behaviour — suitable for handing
to a tax advisor for sign-off — is in
[`docs/umsatzsteuerliche-behandlung.md`](docs/umsatzsteuerliche-behandlung.md)
(German; a rendered `docs/umsatzsteuerliche-behandlung.pdf` is provided alongside).

### Purchase receipt (Kaufbeleg) & bookkeeping copy

Every **online** purchase (Mollie or bank transfer) produces a receipt PDF once
the payment is confirmed — generated by `ReceiptService` at the same moment the
buyer confirmation is sent, never before money has arrived. It is attached to the
buyer's confirmation email and, when an address is configured, copied to a
bookkeeping inbox as a neutral mail with just the PDF (for a **DATEV
Belegtransfer** mailbox, the tax advisor, or **Paperless**).

The receipt is a **GoBD-suitable Beleg, not a VAT invoice**: for a multi-purpose
voucher it shows **no VAT** and carries the statutory § 3 Abs. 15 UStG note (VAT
arises only on redemption) — this avoids the § 14c trap of owing any VAT shown on
the sale. For a single-purpose voucher it splits net / VAT / gross at the
configured rate. The seller identity (legal name, address, tax number) is set
under **Settings → Receipt**; the receipt number is the order's stable
`GS-<id>`. On-site till sales are **not** covered here — those are receipted by
the restaurant's TSE cash register.

> The Mehr-/Einzweckgutschein classification, the exact receipt wording and the
> VAT treatment of the shipping service fee must be confirmed with the tax
> advisor.

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
The Orders list has a **DATEV export** button (booking batch for the year) and a
bulk **Anonymize** action (GDPR erasure, see below).

### Accounting export (operator-agnostic)

A **DATEV-Format booking batch** (EXTF v700) of the voucher *sales* can be
exported for the operator's own accounting / tax advisor — bank- and
software-neutral, no tie to any specific bank. One booking per paid order: the
money account (Soll) to the voucher-liability account (Haben), **no VAT** (the
multi-purpose sale is not a taxable supply). Account numbers + consultant/client
number are configured under **Settings → Receipt → DATEV** (confirm with the tax
advisor — see [`docs/umsatzsteuerliche-behandlung.md`](docs/umsatzsteuerliche-behandlung.md)).
Redemptions are booked by the restaurant's TSE register, not here.

### GDPR data lifecycle

- **IP retention:** the buyer IP (abuse audit) is pruned after `ip_retention_days`.
- **Order anonymization (Art. 17 DSGVO):** `VoucherOrder::anonymize()` nulls the
  buyer's personal data (name, email, address, message, IP) while keeping the
  fiscal record (amounts, payment id, dates) for the statutory retention period
  (§ 147 AO / Art. 17 (3) (b) DSGVO). Available as a backend bulk action (on-demand
  erasure requests) and as a scheduled sweep gated by `personal_data_retention_days`
  (default 0 = off).

### Console

```bash
php artisan jumplink:vouchers-verify          # assert the balance invariant for every voucher
php artisan jumplink:vouchers-verify --fix    # recompute any drifted cached balances from the ledger
php artisan jumplink:vouchers-datev-export --year=2026      # DATEV booking batch (EXTF) for a year
php artisan jumplink:vouchers-anonymize-orders --dry-run    # GDPR sweep (uses personal_data_retention_days)
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
full-redemption status transition, idempotency and the balance invariant;
voucher issuance (atomic numbering, digital/physical type, webhook idempotency);
the `paid`/`failed` webhook paths; PDF + QR rendering; and code/token validity
plus tamper rejection. Mollie is mocked at the `PaymentService` seam, so the
tests never touch the network.

## Security

- **Rate limiting:** the purchase handler is throttled per IP (20/min); the public
  API routes (webhook, status poll, PDF/PNG, scan) carry per-IP `throttle`
  middleware. Redemption is gated by a backend login + permission and an
  idempotency key.
- **Honeypot + server-side validation** on the purchase endpoint; the server
  always recomputes the total in integer cents.
- **Mollie webhook** never trusts the request body — it re-fetches the payment by
  id, verifies the captured amount equals the order total, and looks the order up
  strictly by the stored payment id (no trust in payment metadata). One paid order
  issues exactly one voucher: idempotent under a row lock, with a unique
  `order_id` index as the database-level backstop.
- **Signed, time-limited URLs** (7 days) for the voucher image/PDF download. The
  QR encodes an HMAC token bound to the voucher id (per-voucher secret + an app
  pepper), so a leaked code/number cannot be forged into a redemption link.
- **Return page** is gated by an unguessable per-order access token (not the
  enumerable id); the status poll sends that token in the POST body, not the URL.
- **Data minimization (GDPR):** digital vouchers need no postal address; the buyer
  IP is captured for abuse auditing and then deleted after a configurable window
  (`ip_retention_days`, default 90) by the scheduled `jumplink:vouchers-prune-ips`
  command.
- **Secrets** stay in `.env` (`MOLLIE_API_KEY`, `VOUCHER_TOKEN_SECRET`); nothing
  sensitive is committed.

### Before going live

Run `php artisan jumplink:vouchers-production-check`. It verifies the mandatory
deployment config: `VOUCHER_TOKEN_SECRET` set (the QR-token pepper), a **live**
Mollie key (only when online payment is offered), bank details when bank transfer
is offered, the seller identity for purchase receipts, `APP_DEBUG` off, and HTTPS.
`VOUCHER_TOKEN_SECRET` is **required** — without it the redemption tokens run
unpeppered (the plugin logs an error at boot).

## Contributing

Issues and pull requests are welcome. Please keep money in integer cents, treat
the redemption ledger as append-only, and add a test for any change to the
numbering, balance or token logic.

## License

[MIT](LICENSE) © JumpLink – Art+Code Studio
