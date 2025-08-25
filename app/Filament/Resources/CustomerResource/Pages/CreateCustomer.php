<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'Müşteri Oluştur';
    protected static ?string $breadcrumb = 'Oluştur';

    /** Sadece “Oluştur” ve “İptal” — ikinci butonu kaldırıyoruz. */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Oluştur'),
            $this->getCancelFormAction()->label('İptal'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['name'])) {
            $data['name'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: $data['email'];
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            $data['password'] = Hash::make(Str::random(16));
        }

        return $data;
    }
}
