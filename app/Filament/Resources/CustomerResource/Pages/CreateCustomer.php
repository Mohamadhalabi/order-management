<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Derive display name if missing
        if (empty($data['name'])) {
            $data['name'] = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: $data['email'];
        }

        // Hash password if provided, else auto-generate random one
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            $data['password'] = Hash::make(Str::random(16));
        }

        // Local customer created from admin: wc_id can stay null; wc_synced_at null
        return $data;
    }
}
