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
     * (Avoid Filament\Actions\CreateAction here to prevent the modal + Form=null error)
     */
    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('Oluştur')
                ->submit('create')     // submit the page form
                ->color('primary'),

            Actions\Action::make('cancel')
                ->label('İptal')
                ->url(static::getResource()::getUrl('index')),
        ];
    }

    /**
     * Hesaplamalar ve hazır değerler (oluşturma öncesi).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // RAW state (repeater items dâhil)
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
        $data['created_by_id'] = Auth::id();

        // (İstersen müşteri adresinden doldurma burada da yapılabilir)
        return $data;
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
