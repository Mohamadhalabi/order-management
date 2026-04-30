<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Siparişi Görüntüle';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pdf')
                ->label('PDF İndir')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => route('orders.pdf', ['order' => $this->record, 't' => time()]), shouldOpenInNewTab: true),
        ];
    }
}