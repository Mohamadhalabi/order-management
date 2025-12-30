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

        $brand = $this->brandMeta();
        $code = $order->currency_code ?: 'USD';
        $sym  = Currency::symbolFor($code) ?: $code;

        // 1. Render the View
        $html = view('pdf.order', compact('order', 'brand', 'code', 'sym'))->render();

        // 🔥 THE FIX: Convert Turkish chars to HTML entities
        // This forces DomPDF to render the correct character regardless of encoding settings.
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        // 2. Load the converted HTML
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        // 3. Return with "No-Cache" Headers (Fix 2)
        // We use $pdf->output() instead of stream() to attach custom headers manually.
        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"siparis-{$order->id}.pdf\"")
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
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

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        // 3. Return with "No-Cache" Headers (Fix 2)
        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"siparis-{$order->id}.pdf\"")
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
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