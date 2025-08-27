{{-- resources/views/pdf/order.blade.php --}}
@php
    use Illuminate\Support\Str;

    /** Money formatter: 1 234,56 */
    $fmt = fn($v) => number_format((float) $v, 2, ',', '.');

    /** Build a Dompdf-friendly path/URL for images */
    $toPath = function (?string $path) {
        if (!$path) return null;
        if (Str::startsWith($path, ['data:', 'http://', 'https://'])) return $path;
        $relative = Str::startsWith($path, 'storage/') ? $path : ('storage/' . ltrim($path, '/'));
        return public_path($relative);
    };

    // Back-compat
    $toUrl = $toPath;

    $brand = '#2D83B0';

    // Data helpers
    $items     = $items     ?? ($order->items ?? collect());
    $customer  = $customer  ?? ($order->customer ?? null);
    $creator   = $creator   ?? ($order->creator  ?? null);
    $createdAt = optional($order->created_at)->timezone(config('app.timezone', 'UTC'));

    // ---- Totals (KDV dahil) ----
    $calcSubtotal = function() use ($items) {
        return (float) $items->sum(function($r){
            $q = (float) ($r->qty ?? 1);
            $p = (float) ($r->unit_price ?? 0);
            return $q * $p;
        });
    };

    $subtotal    = isset($order->subtotal) ? (float)$order->subtotal : $calcSubtotal();
    $shipping    = (float)($order->shipping_amount ?? 0);
    $kdvPercent  = (float)($order->kdv_percent ?? 0);
    $kdvAmount   = isset($order->kdv_amount) ? (float)$order->kdv_amount : round($subtotal * $kdvPercent / 100, 2);
    $grandTotal  = (float)($order->total ?? ($subtotal + $shipping + $kdvAmount));

    // number formatting for percent (no currency symbol)
    $fmtPct = function ($v) {
        // trim trailing zeros like 18,00 -> 18
        $s = number_format((float)$v, 2, ',', '.');
        $s = rtrim(rtrim($s, '0'), ',');
        return $s;
    };
@endphp

<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>Sipariş #{{ $order->id }}</title>
    <style>
        @page { margin: 24mm 18mm 22mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1,h2,h3,h4 { margin: 0; }
        .brand { color: {{ $brand }}; }
        .muted { color: #666; }
        .small { font-size: 10px; }
        .xs { font-size: 9px; }

        .header { display: table; width: 100%; margin-bottom: 14px; }
        .header .left { display: table-cell; vertical-align: top; width: 60%; }
        .header .right { display: table-cell; vertical-align: top; text-align: right; width: 40%; }
        .logo { height: 36px; margin-bottom: 6px; }

        .rule { height: 2px; background: {{ $brand }}; opacity: .15; margin: 8px 0 14px; }

        .panel { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #eef2ff; color: #374151; border: 1px solid #e5e7eb; font-size: 10px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; border: 1px solid #e5e7eb; vertical-align: middle; }
        th { background: #f8fafc; font-weight: 700; text-align: left; }
        td.right, th.right { text-align: right; }
        .sku { font-weight: 700; white-space: nowrap; }
        .qty { text-align: center; width: 48px; }
        .thumb { width: 44px; height: 44px; object-fit: cover; border-radius: 6px; display: block; }

        .totals { width: 320px; margin-left: auto; }
        .totals td { border-left: none; border-right: none; }
        .totals tr:first-child td { border-top: 1px solid #e5e7eb; }
        .totals tr:last-child td { border-bottom: 1px solid #e5e7eb; }
        .totals .label { color: #374151; }
        .totals .grand { font-weight: 700; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <div class="left">
            <img class="logo" src="{{ public_path('images/Logo-Normal.webp') }}" alt="Logo">
            <h2 class="brand">Anadolu Anahtar</h2>
            <div class="small muted">
                Kuyuluk, Fındıkpınarı Cd. No:70, 33330 Mezitli/Mersin · Tel: +905524368030 · E-posta: Satis@aanahtar.com.tr
            </div>
        </div>
        <div class="right">
            <h3 class="brand">Sipariş #{{ $order->id }}</h3>
            <div class="small">Tarih: {{ $createdAt?->format('d.m.Y H:i') }}</div>
            @if(!empty($order->status))
                <div class="badge" style="margin-top:6px;">{{ strtoupper($order->status) }}</div>
            @endif
        </div>
    </div>

    <div class="rule"></div>

    {{-- CUSTOMER INFO --}}
    <div class="panel">
        <h4 class="brand" style="margin-bottom:6px;">Müşteri Bilgileri</h4>
        <table style="border: none; width: 100%; border-collapse: separate;">
            <tr>
                <td style="border:none; padding: 0 6px 2px 0;"><strong>Ad:</strong></td>
                <td style="border:none; padding: 0 0 2px 0;">
                    {{ $order->billing_name ?? ($customer->name ?? '—') }}
                </td>
            </tr>
            <tr>
                <td style="border:none; padding: 0 6px 2px 0;"><strong>E-posta:</strong></td>
                <td style="border:none; padding: 0 0 2px 0;">
                    {{ $customer->email ?? '—' }}
                </td>
            </tr>
            <tr>
                <td style="border:none; padding: 0 6px 2px 0;"><strong>Telefon:</strong></td>
                <td style="border:none; padding: 0 0 2px 0;">
                    {{ $order->billing_phone ?? ($customer->phone ?? '—') }}
                </td>
            </tr>
            <tr>
                <td style="border:none; padding: 0 6px 0 0;"><strong>Adres:</strong></td>
                <td style="border:none; padding: 0;">
                    {{ $order->billing_address_line1 ?? '' }}
                    @if(!empty($order->billing_address_line2)) , {{ $order->billing_address_line2 }} @endif
                    @if(!empty($order->billing_city)) , {{ $order->billing_city }} @endif
                    @if(!empty($order->billing_state)) , {{ $order->billing_state }} @endif
                    @if(!empty($order->billing_postcode)) , {{ $order->billing_postcode }} @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ITEMS TABLE --}}
    <h4 class="brand" style="margin: 10px 0 6px;">Kalemler</h4>
    <table>
        <thead>
            <tr>
                <th style="width:64px;">Görsel</th>
                <th>Ürün</th>
                <th class="sku">SKU</th>
                <th class="qty">Adet</th>
                <th class="right">Birim Fiyat</th>
                <th class="right">Tutar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $row)
                @php
                    $name  = $row->product_name  ?? optional($row->product)->name  ?? '';
                    $sku   = $row->sku           ?? optional($row->product)->sku   ?? '';
                    $img   = $row->image_url     ?? optional($row->product)->image ?? null;
                    $qty   = (float) ($row->qty ?? 1);
                    $price = (float) ($row->unit_price ?? 0);
                    $line  = $qty * $price;
                @endphp
                <tr>
                    <td>
                        @if($img && $toPath($img))
                            <img class="thumb" src="{{ $toPath($img) }}" alt="">
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:600; margin-bottom:2px;">{{ $name }}</div>
                    </td>
                    <td class="sku">{{ $sku ?: '—' }}</td>
                    <td class="qty">{{ (int) $qty }}</td>
                    <td class="right">₺ {{ $fmt($price) }}</td>
                    <td class="right">₺ {{ $fmt($line) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TOTALS (KDV dahil) --}}
    <table class="totals" style="margin-top:10px;">
        <tr>
            <td class="label">Ara Toplam</td>
            <td class="right">₺ {{ $fmt($subtotal) }}</td>
        </tr>
        <tr>
            <td class="label">Kargo</td>
            <td class="right">₺ {{ $fmt($shipping) }}</td>
        </tr>
        <tr>
            <td class="label">KDV %</td>
            <td class="right">{{ $fmtPct($kdvPercent) }} %</td>
        </tr>
        <tr>
            <td class="label">KDV (TRY)</td>
            <td class="right">₺ {{ $fmt($kdvAmount) }}</td>
        </tr>
        <tr>
            <td class="label grand">Toplam</td>
            <td class="right grand">₺ {{ $fmt($grandTotal) }}</td>
        </tr>
    </table>

    <div class="footer" style="margin-top:12mm; color:#6b7280; font-size:10px; text-align:center;">
        Herhangi bir sorunuz için bizimle iletişime geçin: <strong>Satis@aanahtar.com.tr</strong> · (+90) 552 436 80 30
    </div>

    {{-- Page numbers (requires enable_php=true in config/dompdf.php) --}}
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("DejaVu Sans", "normal");
            $size = 9;
            $text = "Sayfa {PAGE_NUM} / {PAGE_COUNT}";
            $w = $fontMetrics->get_text_width($text, $font, $size);
            $pdf->page_text(520 - $w, 820, $text, $font, $size, [0.4,0.4,0.4]);
        }
    </script>
</body>
</html>
