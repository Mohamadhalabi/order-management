<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderPdfController extends Controller
{
    public function show(Order $order)
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin','seller']), 403);

        // Load everything we need
        $order->load(['items.product', 'customer', 'creator']);

        // Fallback: ensure each row has a unit price
        foreach ($order->items as $item) {
            if ((float) $item->unit_price <= 0) {
                $item->unit_price = (float) ($item->product?->sale_price ?: $item->product?->price ?: 0);
            }
        }

        $brand = [
            'name'    => 'Anadolu Anahtar',
            'logo'    => public_path('images/Logo-Normal.webp'),
            'address' => 'Kuyuluk, Fındıkpınarı Cd. No:70, 33330 Mezitli/Mersin',
            'phone'   => '(+90) 552 436 80 30',
            'email'   => 'Satis@aanahtar.com.tr',
            'color'   => '#2D83B0',
        ];

        return Pdf::loadView('pdf.order', compact('order', 'brand'))
            ->setPaper('a4', 'portrait')
            ->stream("siparis-{$order->id}.pdf");
    }

    public function __invoke(Order $order)
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin','seller']), 403);

        $order->load(['items.product', 'customer', 'creator']);

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        $brand = [
            'name'    => 'Anadolu Anahtar',
            'logo'    => public_path('images/Logo-Normal.webp'),
            'address' => 'Kuyuluk, Fındıkpınarı Cd. No:70, 33330 Mezitli/Mersin',
            'phone'   => '(+90) 552 436 80 30',
            'email'   => 'Satis@aanahtar.com.tr',
            'color'   => '#2D83B0',
        ];

        return Pdf::loadView('pdf.order', compact('order', 'brand'))
            ->setPaper('a4', 'portrait')
            ->stream("siparis-{$order->id}.pdf");
    }
}
