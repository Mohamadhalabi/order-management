<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    // Türkçe başlıklar
    protected static ?string $title = 'Sipariş Oluştur';
    protected static ?string $breadcrumb = 'Oluştur';

    /** Submit / Cancel buttons */
    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('Oluştur')
                ->submit('create')
                ->color('primary'),

            Actions\Action::make('cancel')
                ->label('İptal')
                ->url(static::getResource()::getUrl('index')),
        ];
    }

    /**
     * Kaydetmeden önce tutarları garantiye al ve minimum 1 kalem zorunlu kıl.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $state   = $this->form->getRawState() ?: request()->input('data', []);
        $payload = array_merge($data, $state);

        // En az 1 ürün şartı
        $items = $payload['items'] ?? [];
        if (empty($items)) {
            \Filament\Notifications\Notification::make()
                ->title('Siparişe en az 1 ürün eklemelisiniz.')
                ->danger()
                ->send();

            $this->addError('data.items', 'Siparişe en az 1 ürün eklemelisiniz.');
            $this->halt();
        }

        // Sunucu tarafında toplamları yeniden hesapla
        $payload = \App\Filament\Resources\OrderResource::recomputeTotalsFromArray($payload);

        // Oluşturan kullanıcı
        $payload['created_by_id'] = \Illuminate\Support\Facades\Auth::id();

        return $payload;
    }

    /**
     * Kayıt oluşunca stokları düş ve PDF oluştur.
     */
    protected function afterCreate(): void
    {
        $order = $this->record->fresh(['items.product', 'customer', 'creator']);

        // ---- STOK DÜŞÜŞÜ ----
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product) {
                    continue;
                }

                $qty = (int) ($item->qty ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                // Hangi sütun stok tutuyor? (stock | stock_quantity | quantity)
                $stockColumn = null;
                foreach (['stock', 'stock_quantity', 'quantity'] as $c) {
                    if (array_key_exists($c, $product->getAttributes()) || isset($product->{$c})) {
                        $stockColumn = $c;
                        break;
                    }
                }
                $stockColumn ??= 'stock';

                $current = (int) ($product->{$stockColumn} ?? 0);
                $new     = max($current - $qty, 0);

                // Üründe yeni stoğu sessizce kaydet
                $product->forceFill([$stockColumn => $new])->saveQuietly();

                // Kalemde yeni kalan stok bilgisini sakla
                $item->stock_snapshot = $new;
                $item->saveQuietly();
            }
        });

        // ---- PDF ----
        $pdf = Pdf::loadView('pdf.order', ['order' => $order->fresh(['items.product', 'customer', 'creator'])]);

        $path = "orders/{$order->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        $order->updateQuietly(['pdf_path' => $path]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
