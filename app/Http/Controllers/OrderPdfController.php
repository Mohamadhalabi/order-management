<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Currency;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderPdfController extends Controller
{
    public function show(Order $order)
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin','seller']), 403);

        $order->load(['items.product', 'customer', 'creator']);

        // Unit price fallback
        // foreach ($order->items as $item) {
        //     if ((float) $item->unit_price <= 0) {
        //         $item->unit_price = (float) ($item->product?->sale_price ?: $item->product?->price ?: 0);
        //     }
        // }

        $brand = $this->brandMeta();
        $code = $order->currency_code ?: 'USD';
        $sym  = Currency::symbolFor($code) ?: $code;

        // 1. Render the View
        $html = view('pdf.order', compact('order', 'brand', 'code', 'sym'))->render();

        // 🔥 THE FIX: Convert Turkish chars to HTML entities (e.g. 'ı' becomes '&#305;')
        // This forces DomPDF to render the correct character regardless of encoding settings.
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        // 2. Load the converted HTML
        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->stream("siparis-{$order->id}.pdf");
    }

    // Apply the same fix to the __invoke method if you use it
    public function __invoke(Order $order)
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin','seller']), 403);
        
        $order->load(['items.product', 'customer', 'creator']);
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        $brand = $this->brandMeta();
        $code = $order->currency_code ?: 'USD';
        $sym  = Currency::symbolFor($code) ?: $code;

        $html = view('pdf.order', compact('order', 'brand', 'code', 'sym'))->render();
        
        // 🔥 THE FIX applied here too
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->stream("siparis-{$order->id}.pdf");
    }

    private function brandMeta(): array
    {
        return [
            'name'    => 'Anadolu Anahtar',
            'logo'    => public_path('images/Logo-Normal.webp'),
            'address' => 'Kuyuluk, Fındıkpınarı Cd. No:70, 33330 Mezitli/Mersin',
            'phone'   => '(+90) 552 436 80 30',
            'email'   => 'Satis@aanahtar.com.tr',
            'color'   => '#2D83B0',
        ];
    }
}