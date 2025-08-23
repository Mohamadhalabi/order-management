<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order #{{ $order->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Order #{{ $order->id }}</h1>

    <p><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
    <p><strong>Created By:</strong> {{ $order->creator?->name }}</p>

    <h3>Customer</h3>
    <p>
        {{ $order->customer?->name }}<br>
        {{ $order->customer?->email }} — {{ $order->customer?->phone }}
    </p>

    <h3>Shipping</h3>
    <p>
        {{ $order->shipping_name }}<br>
        {{ $order->shipping_phone }}<br>
        {{ $order->shipping_address_line1 }} {{ $order->shipping_address_line2 }}<br>
        {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_postcode }}<br>
        {{ $order->shipping_country }}
    </p>

    <h3>Items</h3>
    <table>
        <thead>
            <tr><th>SKU</th><th>Product</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Line</th></tr>
        </thead>
        <tbody>
            @foreach($order->items as $it)
                @php
                    $qty = (float)$it->qty;
                    $unit = (float)$it->unit_price;
                @endphp
                <tr>
                    <td>{{ $it->sku }}</td>
                    <td>{{ $it->product_name ?? $it->product?->name }}</td>
                    <td class="right">{{ $qty }}</td>
                    <td class="right">{{ number_format($unit, 2) }}</td>
                    <td class="right">{{ number_format($qty * $unit, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="right">Total</th>
                <th class="right">{{ number_format((float)$order->total, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    @if($order->notes)
        <p><strong>Notes:</strong><br>{!! nl2br(e($order->notes)) !!}</p>
    @endif
</body>
</html>
