{{-- Voucher PDF, rendered by PdfService via barryvdh/laravel-dompdf.
     Variables: voucher (Voucher), qr (PNG data-URI), brand_name, accent, footer. --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #222; }
        .card { border: 2px solid {{ $accent ?? '#1a3a5a' }}; border-radius: 8px; padding: 24px; }
        .brand { color: {{ $accent ?? '#1a3a5a' }}; font-size: 22px; font-weight: bold; }
        .value { font-size: 40px; font-weight: bold; margin: 12px 0; }
        .code { font-size: 20px; letter-spacing: 2px; }
        .qr { float: right; }
        .muted { color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="card">
        @if(!empty($qr))<img class="qr" src="{{ $qr }}" width="120" height="120" alt="QR">@endif
        <div class="brand">{{ $brand_name ?? '' }}</div>
        <div class="value">Gutschein über {{ $voucher->initial_value_euro }}</div>
        <div class="code">{{ $voucher->code }}</div>
        @if($voucher->recipient_name)<p>Für: {{ $voucher->recipient_name }}</p>@endif
        @if($voucher->valid_until)<p class="muted">Gültig bis {{ $voucher->valid_until->format('d.m.Y') }}</p>@endif
        <p class="muted">An der Kasse vorzeigen – ein Restguthaben bleibt erhalten.</p>
        @if(!empty($footer))<p class="muted">{{ $footer }}</p>@endif
    </div>
</body>
</html>
