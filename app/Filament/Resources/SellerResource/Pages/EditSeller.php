<?php
// app/Filament/Resources/SellerResource/Pages/EditSeller.php
namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use Filament\Resources\Pages\EditRecord;

class EditSeller extends EditRecord
{
    protected static string $resource = SellerResource::class;

    protected function afterSave(): void
    {
        if (!$this->record->hasRole('seller')) {
            $this->record->assignRole('seller');
        }
    }
}
