<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Siparişler';     // Page title
    protected static ?string $breadcrumb = 'Liste';     // Breadcrumb

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Yeni Sipariş')   // <-- Custom button text
                ->icon('heroicon-o-plus') // <-- Optional: add plus icon
                ->color('primary'),       // <-- Optional: make button styled primary
        ];
    }
}
