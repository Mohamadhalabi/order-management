<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use ArPHP\I18N\Arabic;

class OrderPdfController extends Controller
{
    public function show(Order $order)
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin','seller']), 403);

        // Eager-load relations
        $order->load(['items.product', 'customer', 'creator']);

        // Ensure unit price fallback
        foreach ($order->items as $item) {
            if ((float) $item->unit_price <= 0) {
                $item->unit_price = (float) ($item->product?->sale_price ?: $item->product?->price ?: 0);
            }
        }

        $brand = $this->brandMeta();

        $html = $this->renderAndShape($order, $brand);

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->stream("siparis-{$order->id}.pdf");
    }

    public function __invoke(Order $order)
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin','seller']), 403);

        $order->load(['items.product', 'customer', 'creator']);

        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        $brand = $this->brandMeta();

        $html = $this->renderAndShape($order, $brand);

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->stream("siparis-{$order->id}.pdf");
    }

    /**
     * Render Blade to HTML, then shape only Arabic segments so DomPDF
     * displays joined glyphs. Keeps Western (English) digits.
     */
    private function renderAndShape(Order $order, array $brand): string
    {
        // 1) Render Blade to a single HTML string
        $html = view('pdf.order', compact('order', 'brand'))->render();

        // 2) Identify Arabic runs and replace them back-to-front
        $arabic = new Arabic();

        // Find [start, end, start, end, ...] positions of Arabic substrings
        $p = $arabic->arIdentify($html);

        // Replace from the end to preserve earlier offsets
        for ($i = count($p) - 1; $i >= 0; $i -= 2) {
            $start  = $p[$i - 1];
            $length = $p[$i] - $start;

            $segment = substr($html, $start, $length);
            // utf8Glyphs(text, max_chars, $hindo=false to keep English digits, $forcertl=true helps in mixed LTR tables)
            $shaped  = $arabic->utf8Glyphs($segment, 50, false, true);

            $html = substr_replace($html, $shaped, $start, $length);
        }

        return $html;
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
