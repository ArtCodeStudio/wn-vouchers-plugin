# Umsatzsteuerliche und buchhalterische Behandlung der Gutscheine

Diese Darstellung beschreibt die umsatzsteuerliche und buchhalterische
Funktionsweise des Gutscheinsystems (Plugin *JumpLink.Vouchers*) für ein in
Deutschland ansässiges gastronomisches Unternehmen (Stand 2026). Sie dient dem
Steuerberater als Grundlage zur Prüfung und Bestätigung; die unter Abschnitt 6
genannten Punkte sind fachlich zu bestätigen bzw. festzulegen.

## 1. Ablauf in Kürze

Über die Website werden Wertgutscheine über einen frei wählbaren Betrag
(z. B. 10–500 €) verkauft. Bezahlt wird per **Überweisung (Vorkasse)** oder
optional **online** (Karte, PayPal, SEPA u. a.). Nach **bestätigtem
Zahlungseingang** wird der Gutschein ausgestellt – digital als Bild/PDF mit
QR-Code per E-Mail oder als Karte per Post – zusammen mit einem **Kaufbeleg als
PDF**. Eingelöst wird der Gutschein später vor Ort an der Kasse (per Code oder
QR-Scan); ein Restguthaben bleibt erhalten.

Steuerlich sind dabei **zwei getrennte Vorgänge** zu unterscheiden:

| Vorgang | Zeitpunkt | Umsatzsteuer | Erfasst durch |
|---------|-----------|--------------|---------------|
| **Verkauf** des Gutscheins | online, bei Zahlungseingang | **kein** steuerbarer Umsatz | das Gutscheinsystem (Kaufbeleg ohne USt-Ausweis) |
| **Einlösung** des Gutscheins | später, vor Ort | **Umsatzsteuer entsteht** (7 % / 19 %) | die **TSE-Kasse** |

## 2. Behandlung als Mehrzweckgutschein (§ 3 Abs. 15 UStG)

Da beim Verkauf noch nicht feststeht, ob der Gutschein später für Speisen (7 %)
oder Getränke/sonstige Leistungen (19 %) eingelöst wird, wird er als
**Mehrzweckgutschein** behandelt:

- Der Verkauf ist **kein steuerbarer Umsatz**; die Umsatzsteuer entsteht erst bei
  der Einlösung (§ 3 Abs. 15 UStG).
- Der Kaufbeleg weist daher **bewusst keine Umsatzsteuer aus** und trägt den
  Hinweis:
  > „Mehrzweckgutschein gemäß § 3 Abs. 15 UStG: In diesem Beleg ist keine
  > Umsatzsteuer ausgewiesen. Die Umsatzsteuer entsteht erst bei Einlösung des
  > Gutscheins."
- Der Beleg ist damit eine **Quittung über den Erwerb eines Gutscheins, keine
  Umsatzsteuer-Rechnung nach § 14 UStG** – auch um einen unrichtigen oder
  unberechtigten Steuerausweis (§ 14c UStG) zu vermeiden.

Der Geldzufluss aus dem Verkauf wird buchhalterisch **nicht als Erlös, sondern als
Verbindlichkeit** behandelt (Verpflichtung zur späteren Leistung), üblicherweise
auf einem Konto „Verbindlichkeiten aus Gutscheinen" (SKR03 1604 / SKR04 3304) bzw.
„erhaltene Anzahlungen".

## 3. Lebenszyklus aus buchhalterischer Sicht

**(a) Verkauf / Geldzufluss.** Die Zahlung geht ein (Bankkonto bei Überweisung,
Zahlungsdienstleister bei Online-Zahlung). Erst dann wird der Gutschein
ausgestellt und der Kaufbeleg erzeugt. Buchung: Geldzufluss gegen Verbindlichkeit,
**ohne Umsatzsteuer**.

**(b) Einlösung.** Bei Verzehr im Restaurant wird der Gutschein als
**Zahlungsmittel** vorgelegt. Den steuerpflichtigen Umsatz – mit der jeweils
korrekten Aufteilung 7 % / 19 % – erfasst die **TSE-Kasse**, wie bei jeder anderen
Zahlung. Als Zahlart wird „Gutschein" gebucht, d. h. es fließt **kein neues Geld**
zu; der Betrag wird **gegen die beim Verkauf gebildete Verbindlichkeit
verrechnet**. So wird der Erlös nicht doppelt erfasst.

**(c) Teileinlösung / Restguthaben.** Ein Gutschein kann in mehreren Schritten
eingelöst werden (z. B. 50 € − 30 € = 20 € Rest). Das System führt dazu ein
fortlaufendes, unveränderbares Guthaben-Verzeichnis (Saldo = Startwert minus Summe
der Einlösungen). Dieses dient der Saldo- und Nachweisführung; die Umsatzsteuer
entsteht bei jeder Teileinlösung erneut über die TSE-Kasse.

**(d) Nichteinlösung / Verfall.** Standardmäßig wird **kein Ablaufdatum** gedruckt;
es gilt die gesetzliche Verjährung von drei Jahren (§§ 195, 199 BGB, Beginn zum
Jahresende des Erwerbs). Wird ein Gutschein nicht eingelöst, ist die gebildete
Verbindlichkeit nach Verjährung erfolgswirksam aufzulösen (sonstiger betrieblicher
Ertrag); umsatzsteuerlich ergeben sich dabei keine Konsequenzen, da mangels
Einlösung kein steuerauslösender Umsatz vorliegt.

## 4. Inhalt des Kaufbelegs

Jeder Online-Kauf erzeugt einen Kaufbeleg als PDF mit:

- **Aussteller (Verkäufer):** Firma, Anschrift, Steuernummer/USt-IdNr.
- **Beleg-Nr.:** fortlaufende, je Bestellung eindeutige Nummer
- **Belegdatum:** Datum des Zahlungseingangs
- **Käufer:** Name, E-Mail (bei Postversand zusätzlich die Anschrift)
- **Position(en):** „Mehrzweckgutschein über X €" (bei Postversand zusätzlich die
  Zeile „Versandkostenpauschale")
- **Gesamtbetrag** sowie **Zahlungsart und -referenz**
- **Rechtlicher Hinweis** zum Mehrzweckgutschein (siehe Abschnitt 2)

Der Kaufbeleg ist bewusst **keine** Rechnung mit Umsatzsteuerausweis.

## 5. Belegfluss zur Buchhaltung

Das System kann **automatisch eine Belegkopie per E-Mail** versenden – eine
neutrale Nachricht mit ausschließlich dem Kaufbeleg-PDF im Anhang. Das
Zielpostfach ist frei wählbar, z. B. ein **DATEV-Belegtransfer-Postfach**, die
**Kanzlei-Adresse** oder ein internes Belegarchiv. So liegt jeder Beleg
vollständig und zeitnah im Buchhaltungs-/DATEV-Workflow vor.

Als Zahlungsnachweise dienen der **Bankkontoauszug** (bei Überweisung; Zuordnung
über die Beleg-Nr. im Verwendungszweck) bzw. die **Abrechnung des
Zahlungsdienstleisters** (bei Online-Zahlung).

## 6. Durch den Steuerberater zu bestätigen bzw. festzulegen

1. **Klassifizierung als Mehrzweckgutschein** (§ 3 Abs. 15 UStG) für ein
   Restaurant mit Einlösungen zu 7 % und 19 %.
2. **Buchung des Geldzuflusses beim Verkauf** als Verbindlichkeit (kein Erlös,
   keine Umsatzsteuer) – konkretes Konto.
3. **Auflösung der Verbindlichkeit bei Einlösung** über die TSE-Zahlart
   „Gutschein", ohne doppelte Erlöserfassung – korrekte Einrichtung.
4. **Form und Wortlaut des Kaufbelegs** (Abschnitte 2 und 4).
5. **Versandkostenpauschale (nur bei Postversand):** Anders als der Gutscheinwert
   ist dies eine bereits erbrachte Leistung. Versandkosten teilen als
   Nebenleistung normalerweise das Steuerschicksal der Hauptleistung; da der
   Gutscheinverkauf aber keine umsatzsteuerliche Leistung ist, wird überwiegend
   vertreten, die Pauschale eigenständig mit 19 % zu versteuern. Offen: Ausweis
   und Buchung. (Derzeit als eigene Position ohne gesonderten Steuerausweis
   geführt.)
6. **Nicht eingelöste / verfallene Gutscheine** – bilanzielle und
   umsatzsteuerliche Behandlung nach Verjährung (Abschnitt 3 d).
7. **Zielpostfach für die Belegkopie** (DATEV / Kanzlei / internes Archiv).

## 7. Rechtsgrundlagen (Stand 2026)

- **Gutschein-Definitionen und Besteuerungszeitpunkt:** § 3 Abs. 13–15 UStG
  (Abs. 14 = Einzweck-, Abs. 15 = Mehrzweckgutschein), konkretisiert in
  Abschn. 3.17 UStAE und im BMF-Schreiben vom 02.11.2020
  (III C 2 – S 7100/19/10001 :002).
- **Steuerausweis:** §§ 14, 14c UStG.
- **Steuersätze bei Einlösung (Gastronomie ab 01.01.2026):** Speisen 7 %,
  Getränke 19 %. Gerade die Kombination beider Sätze begründet die Einordnung als
  Mehrzweckgutschein (Steuersatz bei Ausgabe noch nicht bestimmbar).
- **Verjährung/Gültigkeit:** §§ 195, 199 BGB (drei Jahre, Beginn zum Jahresende
  des Erwerbs).
- **Kassen- und Belegführung:** GoBD; Erfassung im TSE-System über die Zahlart
  „Gutschein" (Kauf bzw. Einlösung).

---

*Diese Darstellung gibt den umgesetzten Stand wieder und dient als Grundlage der
steuerlichen Prüfung; sie ist selbst keine verbindliche steuerliche Beurteilung.
Die anwendbaren Steuersätze und Vorschriften beziehen sich auf das deutsche
Umsatzsteuerrecht (Stand 2026).*
