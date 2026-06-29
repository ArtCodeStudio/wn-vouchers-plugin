<?php namespace JumpLink\Vouchers\Classes;

use Carbon\Carbon;
use JumpLink\Vouchers\Models\VoucherOrder;
use JumpLink\Vouchers\Models\Settings;

/**
 * DATEV-Format "Buchungsstapel" (EXTF, format version 700) export of voucher
 * sales — operator-agnostic: each operator configures their own consultant/client
 * number and account numbers (Settings → Beleg → DATEV). It has no tie to any
 * specific bank or to any private accounting tooling.
 *
 * The voucher value books money account (Soll) to the voucher-liability account
 * (Haben) with NO VAT — the multi-purpose voucher sale is not a taxable supply
 * (§ 3 Abs. 15 UStG); redemption is booked by the restaurant's TSE cash register,
 * not here. The shipping fee, by contrast, is a standard-rated (19 %) supply: when
 * a shipping-revenue account (Settings → DATEV) is configured it is booked on its
 * own taxed line (money -> Erlöse Versand 19 %), so a physical order produces two
 * rows. Without that account the whole total stays on the (VAT-free) liability
 * line and the operator's advisor splits the fee (see
 * docs/umsatzsteuerliche-behandlung.md).
 *
 * The booking record follows the documented EXTF v700 layout (125 columns); only
 * Umsatz / Soll-Haben / Konto / Gegenkonto / Belegdatum / Belegfeld 1 /
 * Buchungstext are populated. Output is Windows-1252 with CRLF, as DATEV expects.
 */
class DatevExportService
{
    /** Column captions of the EXTF v700 booking-batch record (125 columns). */
    public static function captions(): array
    {
        $c = [
            'Umsatz (ohne Soll/Haben-Kz)', 'Soll/Haben-Kennzeichen', 'WKZ Umsatz', 'Kurs',
            'Basisumsatz', 'WKZ Basisumsatz', 'Konto', 'Gegenkonto (ohne BU-Schlüssel)',
            'BU-Schlüssel', 'Belegdatum', 'Belegfeld 1', 'Belegfeld 2', 'Skonto', 'Buchungstext',
            'Postensperre', 'Diverse Adressnummer', 'Geschäftspartnerbank', 'Sachverhalt',
            'Zinssperre', 'Beleglink',
        ];
        for ($i = 1; $i <= 8; $i++) {
            $c[] = "Beleginfo – Art $i";
            $c[] = "Beleginfo – Inhalt $i";
        }
        $c = array_merge($c, [
            'KOST1 – Kostenstelle', 'KOST2 – Kostenstelle', 'Kost Menge', 'EU-Land u. USt-IdNr.',
            'EU-Steuersatz', 'Abw. Versteuerungsart', 'Sachverhalt L+L', 'Funktionsergänzung L+L',
            'BU 49 Hauptfunktionstyp', 'BU 49 Hauptfunktionsnummer', 'BU 49 Funktionsergänzung',
        ]);
        for ($i = 1; $i <= 20; $i++) {
            $c[] = "Zusatzinformation – Art $i";
            $c[] = "Zusatzinformation – Inhalt $i";
        }
        return array_merge($c, [
            'Stück', 'Gewicht', 'Zahlweise', 'Forderungsart', 'Veranlagungsjahr',
            'Zugeordnete Fälligkeit', 'Skontotyp', 'Auftragsnummer', 'Buchungstyp',
            'USt-Schlüssel (Anzahlungen)', 'EU-Mitgliedstaat (Anzahlungen)',
            'Sachverhalt L+L (Anzahlungen)', 'EU-Steuersatz (Anzahlungen)', 'Erlöskonto (Anzahlungen)',
            'Herkunft-Kz', 'Leerfeld', 'KOST-Datum', 'SEPA-Mandatsreferenz', 'Skontosperre',
            'Gesellschaftername', 'Beteiligtennummer', 'Identifikationsnummer', 'Zeichnernummer',
            'Postensperre bis', 'Bezeichnung', 'Kennzeichen', 'Festschreibung', 'Leistungsdatum',
            'Datum Zuord.', 'Fälligkeit', 'Generalumkehr', 'Steuersatz', 'Land',
            'Abrechnungsreferent', 'BVV-Position', 'EU-Mitgliedstaat u. UStID (Ursprung)',
            'EU-Steuersatz (Ursprung)', 'Abw. Skontokonto',
        ]);
    }

    /** Paid/issued orders with a payment date inside [from, to], oldest first. */
    public static function bookableOrders(Carbon $from, Carbon $to)
    {
        return VoucherOrder::where('status', 'issued')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('paid_at')
            ->get();
    }

    /**
     * Build the EXTF booking-batch CSV (Windows-1252, CRLF) for the given orders
     * within [from, to]. Returns the raw bytes ready to write to a .csv file.
     * Pass $now to make the generated-at timestamp deterministic (tests).
     */
    public static function export(iterable $orders, Carbon $from, Carbon $to, ?Carbon $now = null): string
    {
        $now   = $now ?: Carbon::now();
        $cols  = self::captions();
        $width = count($cols);

        $money     = trim((string) Settings::get('datev_money_account', ''));
        $liability = trim((string) Settings::get('datev_voucher_liability_account', ''));
        // Shipping-fee revenue account (19 % USt) + its BU-Schlüssel. When the
        // account is set the fee is booked on its own taxed line; otherwise the
        // whole total stays on the (VAT-free) liability line, as before.
        $shippingRevenue = trim((string) Settings::get('datev_shipping_revenue_account', ''));
        $shippingVatKey  = self::digits(Settings::get('datev_shipping_vat_key', ''));

        $lines   = [];
        $lines[] = self::headerRow($from, $to, $now);
        $lines[] = implode(';', $cols);

        foreach ($orders as $order) {
            $date = $order->paid_at ?: $order->created_at ?: $now;
            $label = $order->vat_mode === 'single_purpose'
                ? 'Einzweckgutschein '
                : 'Mehrzweckgutschein ';

            // The shipping fee is a standard-rated (19 %) supply. When a shipping-
            // revenue account is configured, book it on its own taxed line and the
            // voucher value alone to the liability account; otherwise the whole
            // total stays on the liability line (advisor splits the fee).
            $feeVat       = $order->shippingFeeVat();
            $splitFee     = $feeVat && $shippingRevenue !== '';
            $voucherCents = $splitFee ? (int) $order->face_value_cents : (int) $order->total_cents;

            $row     = array_fill(0, $width, '');
            $row[0]  = self::amount($voucherCents);                // Umsatz (always positive)
            $row[1]  = self::quote('S');                           // Soll on the money account
            $row[6]  = $money;                                     // Konto (Geldkonto/Verrechnungskonto)
            $row[7]  = $liability;                                 // Gegenkonto (Gutschein-Verbindlichkeit)
            // BU-Schlüssel (index 8) stays empty: the voucher sale has no VAT.
            $row[9]  = $date->format('dm');                        // Belegdatum DDMM (year from header)
            $row[10] = self::quote($order->receipt_number);        // Belegfeld 1 = Beleg-Nr. (GS-…)
            $row[13] = self::quote(mb_substr($label . $order->receipt_number, 0, 60)); // Buchungstext

            $lines[] = implode(';', $row);

            if ($splitFee) {
                $fee     = array_fill(0, $width, '');
                $fee[0]  = self::amount($feeVat['gross_cents']);   // Umsatz = gross fee; the VAT key / Automatikkonto derives the tax
                $fee[1]  = self::quote('S');                       // Soll on the money account
                $fee[6]  = $money;                                 // Konto (Geldkonto/Verrechnungskonto)
                $fee[7]  = $shippingRevenue;                       // Gegenkonto (Erlöse Versand 19 %)
                $fee[8]  = $shippingVatKey;                        // BU-Schlüssel for 19 % (SKR-specific; empty for an Automatikkonto)
                $fee[9]  = $date->format('dm');
                $fee[10] = self::quote($order->receipt_number);
                $fee[13] = self::quote(mb_substr('Versandkosten ' . $order->receipt_number, 0, 60));

                $lines[] = implode(';', $fee);
            }
        }

        $csv = implode("\r\n", $lines) . "\r\n";

        // DATEV expects Windows-1252 (ANSI) — the safest, most compatible encoding.
        return mb_convert_encoding($csv, 'Windows-1252', 'UTF-8');
    }

    /** The 31-field EXTF metadata header line (row 1). */
    protected static function headerRow(Carbon $from, Carbon $to, Carbon $now): string
    {
        $exporter = (string) (Settings::get('brand_name') ?: 'JumpLink Vouchers');
        $fields = [
            self::quote('EXTF'),                                  // 1 Kennzeichen
            700,                                                  // 2 Versionsnummer
            21,                                                   // 3 Datenkategorie (Buchungsstapel)
            self::quote('Buchungsstapel'),                        // 4 Formatname
            13,                                                   // 5 Formatversion
            $now->format('YmdHis') . '000',                       // 6 erzeugt am
            '',                                                   // 7 importiert (leer)
            self::quote('GS'),                                    // 8 Herkunft
            self::quote(mb_substr($exporter, 0, 25)),             // 9 exportiert von
            '',                                                   // 10 importiert von
            self::digits(Settings::get('datev_consultant_number')), // 11 Beraternummer
            self::digits(Settings::get('datev_client_number')),     // 12 Mandantennummer
            $from->copy()->startOfYear()->format('Ymd'),          // 13 WJ-Beginn (Kalenderjahr)
            (int) (Settings::get('datev_account_length', 4) ?: 4),// 14 Sachkontenlänge
            $from->format('Ymd'),                                 // 15 Datum von
            $to->format('Ymd'),                                   // 16 Datum bis
            self::quote('Gutschein-Verkäufe ' . $from->format('Y')), // 17 Bezeichnung
            '',                                                   // 18 Diktatkürzel
            1,                                                    // 19 Buchungstyp (1 = Finanzbuchführung)
            '',                                                   // 20 Rechnungslegungszweck
            0,                                                    // 21 Festschreibung (0 = nicht)
            self::quote('EUR'),                                   // 22 WKZ
            '', '', '', '', '', '', '', '', '',                   // 23–31 (leer)
        ];
        return implode(';', $fields);
    }

    /** Cents → "50,00" (comma decimal, no thousands separator), always positive. */
    protected static function amount($cents): string
    {
        return number_format(abs((int) $cents) / 100, 2, ',', '');
    }

    /** Quote a text field and neutralise characters that would break the CSV. */
    protected static function quote($value): string
    {
        $clean = str_replace(['"', ';', "\r", "\n"], ['', ' ', ' ', ' '], (string) $value);
        return '"' . $clean . '"';
    }

    /** Keep only digits (consultant/client numbers); empty string when unset. */
    protected static function digits($value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }
}
