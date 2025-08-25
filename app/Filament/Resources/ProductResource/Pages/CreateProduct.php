<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title      = 'Ürün Oluştur';
    protected static ?string $breadcrumb = 'Oluştur';

    // Remove "Create & create another"
    protected function hasCreateAnother(): bool
    {
        return false;
    }

    // Translate the two visible actions
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Oluştur'),
            $this->getCancelFormAction()->label('İptal'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
