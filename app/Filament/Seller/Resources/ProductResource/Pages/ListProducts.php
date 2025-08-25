<?php

namespace App\Filament\Seller\Resources\ProductResource\Pages;

use App\Filament\Seller\Resources\ProductResource;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Ürünler';
    protected static ?string $breadcrumb = 'Liste';

    protected function getHeaderActions(): array
    {
        return []; // "Yeni Ürün" butonu yok
    }
}
