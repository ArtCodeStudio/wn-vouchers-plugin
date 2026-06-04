{{-- Voucher PDF, rendered by PdfService via barryvdh/laravel-dompdf.
     Variables: voucher (Voucher), qr (PNG data-URI), brand_name, accent, footer,
     logo (data-URI|null), background (full-page "Briefpapier" data-URI|null). --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #222; }
        .sheet { position: relative; width: 210mm; height: 297mm; }
        .bg { position: absolute; top: 0; left: 0; width: 210mm; height: 297mm; }
        /* Inset via top/left + an explicit content width (210 − 2×24); avoids
           relying on box-sizing:border-box, which dompdf applies unreliably and
           which made width:210mm + padding:24mm overflow the page to the right. */
        .content { position: absolute; top: 24mm; left: 24mm; width: 162mm; }
        .qr { position: absolute; top: 24mm; right: 24mm; }
        .logo { max-height: 24mm; margin-bottom: 8mm; }
        .brand { color: {{ $accent ?? '#1a3a5a' }}; font-size: 22pt; font-weight: bold; }
        .value { font-size: 26pt; font-weight: bold; margin: 6mm 0 2mm; }
        .code { font-size: 16pt; letter-spacing: 2px; }
        .muted { color: #555; font-size: 9pt; margin-top: 2mm; }
        .framed { border: 2px solid {{ $accent ?? '#1a3a5a' }}; border-radius: 6px; padding: 14mm; }
    </style>
</head>
<body>
    <div class="sheet">
        @if(!empty($background))<img class="bg" src="{{ $background }}" alt="">@endif
        @if(!empty($qr))<img class="qr" src="{{ $qr }}" width="110" height="110" alt="QR">@endif
        <div class="content">
            <div class="{{ empty($background) ? 'framed' : '' }}">
                @if(!empty($logo))<img class="logo" src="{{ $logo }}" alt="">@endif
                @if(!empty($brand_name))<div class="brand">{{ $brand_name }}</div>@endif
                <div class="value">{{ trans('jumplink.vouchers::lang.voucher_card.value_over', ['value' => $voucher->initial_value_euro]) }}</div>
                <div class="code">{{ $voucher->code }}</div>
                @if($voucher->recipient_name)<p class="muted">{{ trans('jumplink.vouchers::lang.voucher_card.for', ['name' => $voucher->recipient_name]) }}</p>@endif
                @if($voucher->valid_until)<p class="muted">{{ trans('jumplink.vouchers::lang.voucher_card.valid_until', ['date' => $voucher->valid_until->format('d.m.Y')]) }}</p>@endif
                <p class="muted">{{ trans('jumplink.vouchers::lang.voucher_card.till_hint') }}</p>
                @if(!empty($footer))<p class="muted">{{ $footer }}</p>@endif
            </div>
        </div>
    </div>
</body>
</html>
