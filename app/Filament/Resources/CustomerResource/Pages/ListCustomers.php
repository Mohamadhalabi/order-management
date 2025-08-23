<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
    protected static ?string $title = 'Customers';

    protected function getHeaderActions(): array
    {
        // Read-only: hide Create
        return [];
    }
}
