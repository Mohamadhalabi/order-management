<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use Filament\Resources\Pages\EditRecord;

class EditSeller extends EditRecord
{
    protected static string $resource = SellerResource::class;

    protected static ?string $title = 'Satıcıyı Düzenle';
    protected static ?string $breadcrumb = 'Düzenle';

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Kaydet'),
            $this->getCancelFormAction()->label('İptal'),
        ];
    }

    protected function afterSave(): void
    {
        if (! $this->record->hasRole('seller')) {
            $this->record->assignRole('seller');
        }
    }
}
