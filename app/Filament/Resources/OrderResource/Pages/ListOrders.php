<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Siparişler';
    protected static ?string $breadcrumb = 'Liste';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Yeni Sipariş')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }

    // ❌ DO NOT add getTableFilters()
}
