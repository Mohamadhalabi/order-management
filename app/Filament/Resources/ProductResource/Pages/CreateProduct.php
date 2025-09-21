<?php
// app/Filament/Resources/ProductResource/Pages/CreateProduct.php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Branch;
use App\Models\ProductBranchStock;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected array $branchStockData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->branchStockData = $data['branch_stock'] ?? [];
        unset($data['branch_stock']); // not a column on products
        return $data;
    }

    protected function afterCreate(): void
    {
        $product = $this->record;

        foreach (Branch::all() as $branch) {
            $qty = (int) ($this->branchStockData[$branch->id] ?? 0);
            ProductBranchStock::updateOrCreate(
                ['product_id' => $product->id, 'branch_id' => $branch->id],
                ['stock' => $qty],
            );
        }
    }
}
