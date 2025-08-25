<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSeller extends CreateRecord
{
    protected static string $resource = SellerResource::class;

    protected static ?string $title = 'Satıcı Oluştur';
    protected static ?string $breadcrumb = 'Oluştur';

    // 💡 Hide "Create & create another", translate buttons
    protected function hasCreateAnotherAction(): bool
    {
        return false;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Oluştur'),
            $this->getCancelFormAction()->label('İptal'),
        ];
    }

    protected function afterCreate(): void
    {
        if (! $this->record->hasRole('seller')) {
            $this->record->assignRole('seller');
        }
    }
}
