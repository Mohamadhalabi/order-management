<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf; // barryvdh/laravel-dompdf
use Illuminate\Http\Request;

class OrderPdfController extends Controller
{
    public function show(Order $order)
    {
        // Anyone authenticated can see any order PDF (admin or seller)
        $order->load(['items.product', 'customer', 'creator']);

        // Fallback: make sure each line has a non-zero unit price
        foreach ($order->items as $item) {
            if ((float)$item->unit_price <= 0) {
                $price = $item->product?->sale_price ?: $item->product?->price ?: 0;
                $item->unit_price = (float)$price;
            }
        }

        $pdf = Pdf::loadView('pdf.order', [
            'order' => $order,
            'brand' => [
                'name' => 'Anadolu Anahtar',
                'logo' => public_path('images/Logo-Normal.webp'), // local path for DomPDF
                'address' => "Kuyuluk, Fındıkpınarı Cd. No:70, 33330 Mezitli/Mersin",                  // düzenleyin
                'phone'   => "(+90) 552 436 80 30",                  // düzenleyin
                'email'   => "Satis@aanahtar.com.tr",            // düzenleyin
            ],
        ])->setPaper('a4');

        return $pdf->stream("siparis-{$order->id}.pdf");
    }

    public function __invoke(Order $order)
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin','seller']), 403);

        $order->load(['items.product', 'customer']);

        // Safety margins for large invoices with many images
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.order', compact('order'))
            ->setPaper('a4', 'portrait')
            ->stream("siparis-{$order->id}.pdf");
    }


}
