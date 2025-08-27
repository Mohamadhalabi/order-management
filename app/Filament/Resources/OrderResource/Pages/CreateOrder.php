<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    // Türkçe başlıklar
    protected static ?string $title = 'Sipariş Oluştur';
    protected static ?string $breadcrumb = 'Oluştur';

    /**
     * Use a generic Action that submits the form.
     */
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
     * Kayıttan önce (ara toplam, kdv ve toplam) değerlerini garantiye al.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Formun ham state'ini al (repeater dahil) ve data ile birleştir
        $state   = $this->form->getRawState() ?: request()->input('data', []);
        $payload = array_merge($data, $state);

        // Toplamları tek noktadan hesapla (KDV dahil)
        $payload = OrderResource::recomputeTotalsFromArray($payload);

        // Oluşturan kullanıcı
        $payload['created_by_id'] = Auth::id();

        return $payload;
    }

    /**
     * Kayıt oluşunca PDF üret ve kaydet.
     */
    protected function afterCreate(): void
    {
        $order = $this->record->fresh(['items.product', 'customer']);

        $pdf = Pdf::loadView('pdf.order', ['order' => $order]);

        $path = "orders/{$order->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        $order->update(['pdf_path' => $path]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
