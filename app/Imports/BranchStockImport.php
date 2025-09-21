<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductBranchStock;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BranchStockImport implements ToCollection, WithHeadingRow
{
    protected int $branchId;
    public int $ok = 0;
    public int $skipped = 0;

    public function __construct(int $branchId)
    {
        $this->branchId = $branchId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $sku   = trim((string)($row['sku'] ?? ''));
            $stock = $row['stock'] ?? null;

            if ($sku === '' || $stock === null || $stock === '') {
                $this->skipped++;
                continue;
            }

            // normalize stock to int
            $stock = (int) filter_var($stock, FILTER_SANITIZE_NUMBER_INT);

            // Normalize SKU → uppercase + ensure it starts with AA
            $normSku = strtoupper($sku);
            if (! str_starts_with($normSku, 'AA')) {
                $normSku = 'AA' . $normSku;
            }

            // find product by normalized SKU
            $product = Product::where('sku', $normSku)->first();
            if (! $product) {
                $this->skipped++;
                continue;
            }

            // upsert: one row per product+branch
            ProductBranchStock::updateOrCreate(
                ['product_id' => $product->id, 'branch_id' => $this->branchId],
                ['stock' => $stock]
            );

            $this->ok++;
        }
    }
}
