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

    /** ✅ Block access if status is tamamlandi */
    public function mount($record): void
    {
        parent::mount($record);

        if ($this->record->status === 'tamamlandi') {
            abort(403, 'Bu sipariş tamamlandı ve düzenlenemez.');
        }
    }

    /** Header actions */
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /** Footer form actions */
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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $state   = $this->form->getRawState() ?: request()->input('data', []);
        $payload = array_merge($data, $state);

        // 🚫 Block save when no items
        $items = $payload['items'] ?? [];
        if (empty($items)) {
            \Filament\Notifications\Notification::make()
                ->title('Siparişe en az 1 ürün eklemelisiniz.')
                ->danger()
                ->send();

            // show inline error under the repeater
            $this->addError('data.items', 'Siparişe en az 1 ürün eklemelisiniz.');

            // stop saving gracefully (no 500)
            $this->halt();
        }

        return \App\Filament\Resources\OrderResource::recomputeTotalsFromArray($payload);
    }



    protected function afterSave(): void
    {
        $order = $this->record->fresh(['items.product', 'customer']);

        $pdf = Pdf::loadView('pdf.order', ['order' => $order]);

        $path = $order->pdf_path ?: "orders/{$order->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        $order->updateQuietly(['pdf_path' => $path]);
    }
}
