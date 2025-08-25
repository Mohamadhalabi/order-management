@php
    /** @var \App\Models\Order $order */
    $order = $order->fresh(['items.product','customer']);

    // ---------- BRAND ----------
    $brand = [
        'name'  => 'Sizin Şirket Adı',
        'addr'  => ['Adres Satırı 1','Adres Satırı 2'],
        'phone' => '+90 XXX XXX XX XX',
        'email' => 'info@sirket.com',
        'logo'  => public_path('images/logo.png'),
        'color' => '#0ea5e9', // tema rengi
    ];

    function money_try($v){ return 'TRY ' . number_format((float)$v, 2, '.', ''); }

    // Robust local image -> base64
    use Illuminate\Support\Facades\Storage;
    function img_data(?string $val): ?string {
        if (! $val) return null;

        // Already base64?
        if (str_starts_with($val, 'data:image')) return $val;

        // Absolute file path?
        if (file_exists($val)) {
            $ext = pathinfo($val, PATHINFO_EXTENSION) ?: 'png';
            return 'data:image/'.$ext.';base64,'.base64_encode(file_get_contents($val));
        }

        // Public URL path: /storage/xxx or storage/xxx
        if (str_starts_with($val, '/storage/')) {
            $path = public_path(ltrim($val, '/'));
            if (file_exists($path)) {
                $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'png';
                return 'data:image/'.$ext.';base64,'.base64_encode(file_get_contents($path));
            }
        }
        if (str_starts_with($val, 'storage/')) {
            $path = public_path($val);
            if (file_exists($path)) {
                $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'png';
                return 'data:image/'.$ext.';base64,'.base64_encode(file_get_contents($path));
            }
        }

        // Public disk relative path (e.g. products/foo.jpg)
        try {
            $path = Storage::disk('public')->path($val);
            if (file_exists($path)) {
                $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'png';
                return 'data:image/'.$ext.';base64,'.base64_encode(file_get_contents($path));
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Remote http(s) => DomPDF blocks by default; skip
        return null;
    }

    // Prefer cached item image, fallback to product image
    $itemThumb = function ($item) {
        $candidate = $item->image_url ?: $item->product?->image;
        return img_data($candidate);
    };

    $logo = file_exists($brand['logo']) ? img_data($brand['logo']) : null;

    // Optional service fee
    $serviceFeePercent = 0;
    $serviceFee = $serviceFeePercent ? round(($order->subtotal * $serviceFeePercent) / 100, 2) : 0;
    $grand = (float)$order->total + $serviceFee;
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<title>Sipariş #{{ $order->id }}</title>
<style>
    @page { margin: 22mm 16mm 18mm 16mm; }
    body  { font-family: DejaVu Sans, sans-serif; color:#111827; font-size:12px; }

    /* HEADER STRIP */
    .strip { height: 6mm; background: {{ $brand['color'] }}; border-radius: 6px; }
    .header { margin: 8px 0 14px 0; }
    .row { width:100%; }
    .col { display:inline-block; vertical-align:top; }
    .w-60 { width:60%; }
    .w-40 { width:39%; }

    .brand .name { font-size:18px; font-weight:700; margin:0; }
    .brand .sub  { color:#6b7280; line-height:1.4; }
    .logo { height:50px; margin-right:8px; }

    .meta { border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; }
    .meta table { width:100%; border-collapse:collapse; }
    .meta td { padding:3px 0; }
    .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; background:#eef2ff; color:#3730a3; }
    .badge.paid { background:#d1fae5; color:#065f46; border:1px solid #10b981; }
    .badge.draft { background:#f3f4f6; color:#374151; }

    .card { border:1px solid #e5e7eb; border-radius:10px; padding:12px; }
    h2 { font-size:13px; margin:0 0 6px 0; color:#111827; }

    table.items { width:100%; border-collapse:separate; border-spacing:0; margin-top:10px; }
    .items thead th {
        background:#f8fafc; text-align:left; padding:9px 8px;
        font-size:12px; border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb;
    }
    .items thead th:first-child { border-top-left-radius:8px; }
    .items thead th:last-child  { border-top-right-radius:8px; }
    .items tbody td { padding:8px; border-bottom:1px solid #f1f5f9; }
    .items tbody tr:nth-child(even) td { background:#fbfbfb; }
    .t-center { text-align:center; }
    .t-right  { text-align:right; }
    .w-num { width:24px; }
    .w-sku { width:90px; color:#475569; }
    .w-img { width:62px; }
    .thumb { height:42px; border:1px solid #e5e7eb; border-radius:8px; }

    .summary { width:42%; float:right; margin-top:10px; }
    .summary .panel { border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; background:#fcfcfd; }
    .summary table { width:100%; border-collapse:collapse; }
    .summary td { padding:6px 0; }
    .summary .label { color:#374151; }
    .summary .value { text-align:right; }
    .summary .total { font-weight:700; font-size:13px; }

    .stamp { position:absolute; right:22mm; top: 36mm; font-weight:800; color:{{ $brand['color'] }};
             border:3px solid {{ $brand['color'] }}; padding:8px 14px; border-radius:10px; transform:rotate(-6deg); }

    .footer { position: fixed; left:0; right:0; bottom:10mm; text-align:center; color:#6b7280; font-size:11px; }
    .page:after { content: counter(page) " / " counter(pages); }
</style>
</head>
<body>

<div class="strip"></div>

@if($order->status === 'paid')
    <div class="stamp">ÖDENDİ</div>
@endif

<div class="header">
    <div class="row">
        <div class="col w-60">
            <table style="width:100%;">
                <tr>
                    <td style="width:62px;">
                        @if($logo)<img src="{{ $logo }}" class="logo" alt="Logo">@endif
                    </td>
                    <td class="brand">
                        <p class="name">{{ $brand['name'] }}</p>
                        <div class="sub">
                            @foreach($brand['addr'] as $l) {{ $l }}<br> @endforeach
                            Tel: {{ $brand['phone'] }} — {{ $brand['email'] }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="col w-40">
            <div class="meta">
                <table>
                    <tr><td><strong>Sipariş #</strong></td><td class="t-right">{{ $order->id }}</td></tr>
                    <tr>
                        <td><strong>Durum</strong></td>
                        <td class="t-right">
                            <span class="badge {{ $order->status === 'paid' ? 'paid' : ($order->status === 'draft' ? 'draft' : '') }}">
                                {{ $order->status }}
                            </span>
                        </td>
                    </tr>
                    <tr><td><strong>Tarih</strong></td><td class="t-right">{{ $order->created_at?->format('Y-m-d') }}</td></tr>
                    @if($order->customer)
                        <tr><td><strong>Müşteri ID</strong></td><td class="t-right">{{ $order->customer->id }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row" style="margin-bottom:10px;">
    <div class="col" style="width:50%; padding-right:8px;">
        <div class="card">
            <h2>Müşteri</h2>
            <div><strong>Ad Soyad:</strong> {{ $order->billing_name ?? $order->customer?->name }}</div>
            @if($order->customer?->email)<div><strong>E-posta:</strong> {{ $order->customer->email }}</div>@endif
            @if($order->billing_phone)<div><strong>Telefon:</strong> {{ $order->billing_phone }}</div>@endif
            @if($order->billing_address_line1)
                <div style="margin-top:6px;"><strong>Adres:</strong></div>
                <div>{{ $order->billing_address_line1 }}</div>
                @if($order->billing_address_line2)<div>{{ $order->billing_address_line2 }}</div>@endif
                <div>{{ trim(($order->billing_city ?? '').' '.($order->billing_state ?? '')) }}</div>
                <div>{{ trim(($order->billing_postcode ?? '').' '.($order->billing_country ?? '')) }}</div>
            @endif
        </div>
    </div>
    <div class="col" style="width:50%; padding-left:8px;">
        <div class="card">
            <h2>Gönderim</h2>
            @php
                $ship1 = $order->shipping_address_line1 ?? $order->billing_address_line1;
                $ship2 = $order->shipping_address_line2 ?? $order->billing_address_line2;
                $shipCity = trim(($order->shipping_city ?? $order->billing_city).' '.($order->shipping_state ?? $order->billing_state));
                $shipPost = trim(($order->shipping_postcode ?? $order->billing_postcode).' '.($order->shipping_country ?? $order->billing_country));
            @endphp
            <div>{{ $order->shipping_name ?? $order->billing_name }}</div>
            @if($order->shipping_phone ?? $order->billing_phone)
                <div>{{ $order->shipping_phone ?? $order->billing_phone }}</div>
            @endif
            @if($ship1)
                <div style="margin-top:6px;">{{ $ship1 }}</div>
                @if($ship2)<div>{{ $ship2 }}</div>@endif
                <div>{{ $shipCity }}</div>
                <div>{{ $shipPost }}</div>
            @endif
        </div>
    </div>
</div>

<h2>Ürünler</h2>
<table class="items">
    <thead>
        <tr>
            <th class="w-num t-center">#</th>
            <th class="w-sku">SKU</th>
            <th class="w-img t-center">Görsel</th>
            <th>Ürün</th>
            <th class="t-right">Birim</th>
            <th class="t-center">Adet</th>
            <th class="t-right">Tutar</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $i => $item)
            @php
                $line = (float)($item->qty ?? 0) * (float)($item->unit_price ?? 0);
                $img  = $itemThumb($item);
            @endphp
            <tr>
                <td class="t-center">{{ $i+1 }}</td>
                <td class="w-sku">{{ $item->sku }}</td>
                <td class="t-center">@if($img)<img src="{{ $img }}" class="thumb" alt="img">@endif</td>
                <td>{{ $item->product_name ?? $item->product?->name }}</td>
                <td class="t-right">{{ money_try($item->unit_price) }}</td>
                <td class="t-center">{{ (int) $item->qty }}</td>
                <td class="t-right">{{ money_try($line) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="summary">
    <div class="panel">
        <table>
            <tr>
                <td class="label">Ara Toplam</td>
                <td class="value">{{ money_try($order->subtotal) }}</td>
            </tr>
            <tr>
                <td class="label">Kargo</td>
                <td class="value">{{ money_try($order->shipping_amount) }}</td>
            </tr>
            @if($serviceFeePercent > 0)
            <tr>
                <td class="label">Hizmet Bedeli ({{ $serviceFeePercent }}%)</td>
                <td class="value">{{ money_try($serviceFee) }}</td>
            </tr>
            @endif
            <tr>
                <td class="label total">Toplam</td>
                <td class="value total">{{ money_try($grand) }}</td>
            </tr>
        </table>
    </div>
</div>

@if($order->notes)
    <div style="margin-top:14px;"><strong>Not:</strong> {{ $order->notes }}</div>
@endif

<div class="footer">
    Sayfa <span class="page"></span> • {{ $brand['email'] }}
</div>
</body>
</html>
