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

    /** Kayıttan önce toplamları (KDV dahil) yeniden hesapla */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $state   = $this->form->getRawState() ?: request()->input('data', []);
        $payload = array_merge($data, $state);

        // Toplamları tek yerden hesapla (subtotal, kdv_amount, total)
        $payload = OrderResource::recomputeTotalsFromArray($payload);

        return $payload;
    }

    /** Her kayıttan sonra PDF'i güncelle */
    protected function afterSave(): void
    {
        $order = $this->record->fresh(['items.product', 'customer']);

        $pdf = Pdf::loadView('pdf.order', ['order' => $order]);

        $path = $order->pdf_path ?: "orders/{$order->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        // timestamps’i gereksiz yere değiştirmeyelim
        $order->updateQuietly(['pdf_path' => $path]);
    }
}
