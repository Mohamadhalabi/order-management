<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;
    protected static ?string $title = 'Sipariş Düzenle';
    protected static ?string $breadcrumb = 'Düzenle';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->submit('save')
                ->color('primary'),
            Actions\Action::make('cancel')
                ->label('İptal')
                ->url(static::getResource()::getUrl('index')),
        ];
    }

    /** Re-calc totals before save */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $state = $this->form->getRawState() ?: request()->input('data', []);
        $items = $state['items'] ?? [];

        $subtotal = 0.0;
        foreach ($items as $row) {
            $q = (float)($row['qty'] ?? 0);
            $p = (float)($row['unit_price'] ?? 0);
            $subtotal += $q * $p;
        }
        $shipping = (float)($state['shipping_amount'] ?? $data['shipping_amount'] ?? 0);
        $data['subtotal'] = round($subtotal, 2);
        $data['total']    = round($subtotal + $shipping, 2);

        return $data;
    }

    /** Regenerate PDF every time the order is saved */
    protected function afterSave(): void
    {
        $order = $this->record->fresh(['items.product', 'customer']);

        $pdf = Pdf::loadView('pdf.order', ['order' => $order]);

        $path = $order->pdf_path ?: "orders/{$order->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        // avoid touching timestamps/observers unnecessarily
        $order->updateQuietly(['pdf_path' => $path]);
    }
}
