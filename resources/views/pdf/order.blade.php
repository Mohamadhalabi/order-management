{{-- resources/views/pdf/order.blade.php --}}
@php
    use Illuminate\Support\Str;

    /** Money formatter: 1.234,56 */
    $fmt = fn($v) => number_format((float) $v, 2, ',', '.');

    /** Percent formatter without trailing zeros */
    $fmtPct = function ($v) {
        $s = number_format((float)$v, 2, ',', '.');
        return rtrim(rtrim($s, '0'), ',');
    };

    /** Build a Dompdf-friendly local/public path for images */
    $toPath = function (?string $path) {
        if (!$path) return null;
        if (Str::startsWith($path, ['data:', 'http://', 'https://', '/', 'file://'])) return $path;
        $relative = Str::startsWith($path, 'storage/') ? $path : ('storage/' . ltrim($path, '/'));
        return public_path($relative);
    };

    // Relationships / data
    $items      = $items     ?? ($order->items ?? collect());
    $customer   = $customer  ?? ($order->customer ?? null);
    $creator    = $creator   ?? ($order->creator  ?? null);
    $createdAt  = optional($order->created_at)->timezone(config('app.timezone', 'UTC'));
    $brandColor = $brand['color'] ?? '#2D83B0';

    // Hard-coded QR (smaller)
    $qr = file_exists(public_path('images/qr-code.png')) ? public_path('images/qr-code.png') : null;

    // ---- Totals (mirror server logic) ----
    $calcSubtotal = function() use ($items) {
        return (float) $items->sum(function($r){
            $q = (float) ($r->qty ?? 1);
            $p = (float) ($r->unit_price ?? 0);
            return $q * $p;
        });
    };

    $subtotal        = isset($order->subtotal) ? (float)$order->subtotal : round($calcSubtotal(), 2);
    $shipping        = (float)($order->shipping_amount ?? 0);

    $discountPercent = (float)($order->discount_percent ?? 0);
    $discountSaved   = (float)($order->discount_amount  ?? 0);
    $discountFromPct = round($subtotal * $discountPercent / 100, 2);
    $finalDiscount   = min(max($discountSaved, $discountFromPct), $subtotal);

    $kdvPercent      = (float)($order->kdv_percent ?? 0);
    $taxBase         = max($subtotal - $finalDiscount, 0);           // Toplam after discount
    $kdvAmount       = isset($order->kdv_amount) ? (float)$order->kdv_amount : round($taxBase * $kdvPercent / 100, 2);

    $grandTotal      = isset($order->total) ? (float)$order->total : ($taxBase + $kdvAmount + $shipping); // final
@endphp

<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $brand['name'] ?? 'Fatura' }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; }
        h1,h2,h3,h4 { margin: 0; }
        .brand { color: {{ $brandColor }}; }
        .muted { color: #666; }
        .small { font-size: 10px; }
        .xs { font-size: 9px; }

        .header { display: table; width: 100%; margin-bottom: 14px; }
        .header .left { display: table-cell; vertical-align: top; width: 60%; }
        .header .right { display: table-cell; vertical-align: top; text-align: right; width: 40%; position: relative; }
        .logo { height: 36px; margin-bottom: 6px; }

        .qr { width: 72px; height: 72px; object-fit: contain; display: inline-block; margin-top: 4px; margin-left: 8px; }

        .rule { height: 2px; background: {{ $brandColor }}; opacity: .15; margin: 8px 0 14px; }

        .panel { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #eef2ff; color: #374151; border: 1px solid #e5e7eb; font-size: 10px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; border: 1px solid #e5e7eb; vertical-align: middle; }
        th { background: #f8fafc; font-weight: 700; text-align: left; }
        td.right, th.right { text-align: right; }
        .sku { font-weight: 700; white-space: nowrap; }
        .qty { text-align: center; width: 48px; }
        .thumb { width: 44px; height: 44px; object-fit: cover; border-radius: 6px; display: block; }

        /* Side-by-side blocks */
        .two-col { width: 100%; border: none; border-collapse: separate; table-layout: fixed; }
        .two-col td { border: none; vertical-align: top; padding: 0; }
        .two-col .col { width: 50%; }
        .two-col .left-pad  { padding-right: 6px; }
        .two-col .right-pad { padding-left: 6px; }

        /* Items table can break across pages */
        table.items { page-break-inside: auto; }
        table.items tr { page-break-inside: avoid; page-break-after: auto; }

        /* Totals must stay together */
        .keep-together { page-break-inside: avoid; }

        /* === TOTALS: ONLY HORIZONTAL (bottom) BORDERS BETWEEN ROWS === */
        .totals { width: 360px; margin-left: auto; border-collapse: separate; border-spacing: 0; }
        .totals th, .totals td { padding: 8px 0; border: none !important; }
        .totals tr > td { border-bottom: 1px solid #e5e7eb !important; }
        .totals tr:last-child > td { border-bottom: 1px solid #e5e7eb !important; }

        .totals .label { color: #374151; }
        .totals .val { text-align: right; white-space: nowrap; }
        .totals .grand .label { font-weight: 700; }
        .totals .grand .val   { font-weight: 700; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <div class="left">
            @if(!empty($brand['logo']))
                <img class="logo" src="{{ $brand['logo'] }}" alt="Logo">
            @endif
            <h2 class="brand">{{ $brand['name'] ?? '—' }}</h2>
            <div class="small muted">
                {{ $brand['address'] ?? '' }} · Tel: {{ $brand['phone'] ?? '' }} · E-posta: {{ $brand['email'] ?? '' }}
            </div>
        </div>
        <div class="right">
            <div class="small">Tarih: {{ $createdAt?->format('d.m.Y H:i') }}</div>
            @if(!empty($order->status))
                <div class="badge" style="margin-top:6px;">{{ strtoupper($order->status) }}</div>
            @endif

            {{-- QR at top-right (hard-coded) --}}
            @if($qr)
                <div>
                    <img class="qr" src="{{ $qr }}" alt="QR">
                </div>
            @endif
        </div>
    </div>

    <div class="rule"></div>

    {{-- Müşteri + Oluşturan --}}
    <table class="two-col" style="margin-bottom:12px;">
        <tr>
            <td class="col left-pad">
                <div class="panel">
                    <h4 class="brand" style="margin-bottom:6px;">Müşteri Bilgileri</h4>
                    <table style="border: none; width: 100%; border-collapse: separate;">
                        <tr>
                            <td style="border:none; padding: 0 6px 2px 0;"><strong>Ad:</strong></td>
                            <td style="border:none; padding: 0 0 2px 0;">
                               {{ ($customer->name ?? null) ?: ($order->billing_name ?? '—') }}
                            </td>
                        </tr>
                        <tr>
                            <td style="border:none; padding: 0 6px 0 0;"><strong>Telefon:</strong></td>
                            <td style="border:none; padding: 0;">
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
            </td>
            <td class="col right-pad">
                <div class="panel">
                    <h4 class="brand" style="margin-bottom:6px;">Oluşturan / Satış Temsilcisi</h4>
                    <table style="border:none; width:100%; border-collapse:separate;">
                        <tr>
                            <td style="border:none; padding:0 6px 2px 0;"><strong>Ad:</strong></td>
                            <td style="border:none; padding:0 0 2px 0;">{{ $creator->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td style="border:none; padding:0 6px 0 0;"><strong>Telefon:</strong></td>
                            <td style="border:none; padding:0;">{{ $creator->phone ?? '—' }}</td>
                        </tr>
                        @if(!empty($creator?->email))
                        <tr>
                            <td style="border:none; padding:0 6px 0 0;"><strong>E-posta:</strong></td>
                            <td style="border:none; padding:0;">{{ $creator->email }}</td>
                        </tr>
                        @endif

                        @if(!empty($order->notes))
                        <tr>
                            <td style="border:none; padding:6px 6px 0 0; vertical-align: top;"><strong>Not:</strong></td>
                            <td style="border:none; padding:6px 0 0 0; white-space: pre-wrap;">{{ $order->notes }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- ITEMS TABLE --}}
    <h4 class="brand" style="margin: 10px 0 6px;">Kalemler</h4>
    <table class="items">
        <thead>
            <tr>
                <th style="width:32px; text-align:center;">#</th>
                <th style="width:64px;">Görsel</th>
                <th>Ürün</th>
                <th class="sku">SKU</th>
                <th class="qty">Adet</th>
                <th class="right">Birim Fiyat</th>
                <th class="right">Tutar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $row)
                @php
                    $name  = $row->product_name  ?? optional($row->product)->name  ?? '';
                    $sku   = $row->sku           ?? optional($row->product)->sku   ?? '';
                    $img   = $row->image_url     ?? optional($row->product)->image ?? null;
                    $qty   = (float) ($row->qty ?? 1);
                    $price = (float) ($row->unit_price ?? 0);
                    $line  = $qty * $price;
                @endphp
                <tr>
                    <td style="text-align:center;">{{ $i + 1 }}</td>
                    <td>
                        @if($img && $toPath($img))
                            <img class="thumb" src="{{ $toPath($img) }}" alt="">
                        @endif
                    </td>
                    <td><div style="font-weight:600; margin-bottom:2px;">{{ $name }}</div></td>
                    <td class="sku">{{ $sku ?: '—' }}</td>
                    <td class="qty">{{ (int) $qty }}</td>
                    <td class="right">&nbsp;₺&nbsp;{{ $fmt($price) }}</td>
                    <td class="right">&nbsp;₺&nbsp;{{ $fmt($line) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- TOTALS --}}
    <div class="keep-together" style="margin-top:10px;">
        <table class="totals">
            <tr>
                <td class="label">Ara Toplam</td>
                <td class="val">&nbsp;₺&nbsp;{{ $fmt($subtotal) }}</td>
            </tr>
            <tr>
                <td class="label">İndirim (TRY)</td>
                <td class="val">−&nbsp;₺&nbsp;{{ $fmt($finalDiscount) }}</td>
            </tr>
            <tr>
                <td class="label">Toplam</td>
                <td class="val">&nbsp;₺&nbsp;{{ $fmt($taxBase) }}</td>
            </tr>
            <tr>
                <td class="label">KDV %</td>
                <td class="val">{{ $fmtPct($kdvPercent) }} %</td>
            </tr>
            <tr class="grand">
                <td class="label">Toplam</td>
                <td class="val">&nbsp;₺&nbsp;{{ $fmt($grandTotal) }}</td>
            </tr>
        </table>
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
