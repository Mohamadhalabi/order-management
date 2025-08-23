<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSeller extends CreateRecord
{
    protected static string $resource = SellerResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(), // keep only "Create"
            $this->getCancelFormAction(), // keep "Cancel"
        ];
    }

    protected function afterCreate(): void
    {
        if (!$this->record->hasRole('seller')) {
            $this->record->assignRole('seller');
        }
    }
}
