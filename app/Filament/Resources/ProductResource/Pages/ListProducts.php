<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title      = 'Ürünler';
    protected static ?string $breadcrumb = 'Liste';

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Yeni Ürün')->createAnother(false)];
    }
}
