<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'Müşteriler';
    protected static ?string $breadcrumb = 'Liste';   // 👈 second crumb

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Yeni Müşteri'),
        ];
    }
}
