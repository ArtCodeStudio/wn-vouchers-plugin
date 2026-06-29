{{-- Purchase receipt (Kaufbeleg), rendered by ReceiptService via barryvdh/laravel-dompdf.
     Variables: r (receipt model, see ReceiptService::buildModel), accent, logo
     (data-URI|null), brand_name, euro (cents -> "50,00 €" closure). --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22mm 20mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #222; font-size: 10pt; line-height: 1.45; }
        .head { width: 100%; }
        .head td { vertical-align: top; }
        .logo { max-height: 20mm; }
        .seller { text-align: right; font-size: 9pt; color: #444; }
        .seller .name { font-weight: bold; color: #222; font-size: 10pt; }
        h1 { color: {{ $accent ?? '#1a3a5a' }}; font-size: 18pt; margin: 12mm 0 1mm; }
        .meta { color: #555; font-size: 9pt; margin-bottom: 8mm; }
        .meta strong { color: #222; }
        .block { margin-bottom: 7mm; }
        .label { color: #777; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 1mm; }
        table.items { width: 100%; border-collapse: collapse; margin: 4mm 0; }
        table.items th { text-align: left; border-bottom: 2px solid {{ $accent ?? '#1a3a5a' }}; padding: 2mm 0; font-size: 9pt; }
        table.items th.amount, table.items td.amount { text-align: right; }
        table.items td { padding: 2mm 0; border-bottom: 1px solid #e2e2e2; }
        table.items tr.total td { border-bottom: none; border-top: 2px solid {{ $accent ?? '#1a3a5a' }}; font-weight: bold; padding-top: 3mm; }
        table.items tr.sub td { border-bottom: none; color: #555; padding: 1mm 0; }
        .note { margin-top: 9mm; padding: 4mm; background: #f5f5f3; border-left: 3px solid {{ $accent ?? '#1a3a5a' }}; font-size: 9pt; color: #333; }
        .pay { color: #555; font-size: 9pt; margin-top: 4mm; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>@if(!empty($logo))<img class="logo" src="{{ $logo }}" alt="">@elseif(!empty($brand_name))<strong>{{ $brand_name }}</strong>@endif</td>
            <td class="seller">
                <div class="name">{{ $r['seller']['name'] }}</div>
                @if($r['seller']['address'])<div>{!! nl2br(e($r['seller']['address'])) !!}</div>@endif
                @if($r['seller']['tax_number'])<div>{{ trans('jumplink.vouchers::lang.receipt.seller_tax_label') }} {{ $r['seller']['tax_number'] }}</div>@endif
            </td>
        </tr>
    </table>

    <h1>{{ trans('jumplink.vouchers::lang.receipt.title') }}</h1>
    <div class="meta">
        <strong>{{ trans('jumplink.vouchers::lang.receipt.number_label') }}</strong> {{ $r['number'] }}
        &nbsp;·&nbsp;
        <strong>{{ trans('jumplink.vouchers::lang.receipt.date_label') }}</strong> {{ $r['date']->format('d.m.Y') }}
    </div>

    <div class="block">
        <div class="label">{{ trans('jumplink.vouchers::lang.receipt.buyer_label') }}</div>
        <div>{{ $r['buyer']['name'] }}</div>
        @if($r['buyer']['address'])<div>{!! nl2br(e($r['buyer']['address'])) !!}</div>@endif
        <div>{{ $r['buyer']['email'] }}</div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>{{ trans('jumplink.vouchers::lang.receipt.col_position') }}</th>
                <th class="amount">{{ trans('jumplink.vouchers::lang.receipt.col_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($r['lines'] as $line)
                <tr>
                    <td>{{ $line['label'] }}</td>
                    <td class="amount">{{ $euro($line['cents']) }}</td>
                </tr>
            @endforeach

            @if($r['vat'])
                <tr class="sub">
                    <td>{{ trans('jumplink.vouchers::lang.receipt.net_label') }}</td>
                    <td class="amount">{{ $euro($r['vat']['net_cents']) }}</td>
                </tr>
                <tr class="sub">
                    <td>{{ trans('jumplink.vouchers::lang.receipt.vat_label', ['rate' => $r['vat']['rate'] == (int) $r['vat']['rate'] ? (int) $r['vat']['rate'] : $r['vat']['rate']]) }}</td>
                    <td class="amount">{{ $euro($r['vat']['vat_cents']) }}</td>
                </tr>
            @endif

            @if($r['fee_vat'])
                <tr class="sub">
                    <td>{{ trans('jumplink.vouchers::lang.receipt.fee_net_label') }}</td>
                    <td class="amount">{{ $euro($r['fee_vat']['net_cents']) }}</td>
                </tr>
                <tr class="sub">
                    <td>{{ trans('jumplink.vouchers::lang.receipt.fee_vat_label', ['rate' => $r['fee_vat']['rate'] == (int) $r['fee_vat']['rate'] ? (int) $r['fee_vat']['rate'] : $r['fee_vat']['rate']]) }}</td>
                    <td class="amount">{{ $euro($r['fee_vat']['vat_cents']) }}</td>
                </tr>
            @endif

            <tr class="total">
                <td>{{ trans('jumplink.vouchers::lang.receipt.total_label') }}</td>
                <td class="amount">{{ $euro($r['total_cents']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="pay">
        <strong>{{ trans('jumplink.vouchers::lang.receipt.payment_label') }}</strong> {{ trans($r['payment']['method_key']) }}
        &nbsp;·&nbsp;
        <strong>{{ trans('jumplink.vouchers::lang.receipt.reference_label') }}</strong> {{ $r['payment']['reference'] }}
    </div>

    <div class="note">
        {{ trans($r['note_key']) }}@if($r['fee_vat']) {{ trans('jumplink.vouchers::lang.receipt.note_fee_vat') }}@endif
        @if($r['extra_note'])<br><br>{{ $r['extra_note'] }}@endif
    </div>
</body>
</html>
