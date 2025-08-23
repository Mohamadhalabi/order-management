<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class ProductsImport implements OnEachRow, WithHeadingRow
{
    /**
     * Options:
     * - $createNew: create a product when SKU not found
     * - $updateMeta: update name/price/sale_price/image for existing SKU when provided
     */
    public function __construct(
        public bool $createNew = true,
        public bool $updateMeta = true,
    ) {}

    /**
     * Expected headers (case-insensitive):
     * sku, name, stock, price, sale_price, image
     */
    public function onRow(Row $row): void
    {
        $data = array_change_key_case($row->toArray(), CASE_LOWER);

        $sku = trim((string) Arr::get($data, 'sku', ''));
        if ($sku === '') return;

        $name       = trim((string) Arr::get($data, 'name', ''));
        $stock      = (int)   Arr::get($data, 'stock', 0);
        $price      = (float) Arr::get($data, 'price', 0);
        $salePrice  = (float) Arr::get($data, 'sale_price', 0);
        $image      = trim((string) Arr::get($data, 'image', ''));

        $product = Product::firstOrNew(['sku' => $sku]);

        // Always update stock count
        // NOTE: if your DB column is 'stock_quantity', change 'stock' to 'stock_quantity' below.
        $product->stock = $stock;

        if (! $product->exists) {
            if (! $this->createNew) {
                // Skip creating new products when the switch is off
                return;
            }

            // Fill required/new fields
            $product->name        = $name !== '' ? $name : $sku;
            $product->price       = $price;
            $product->sale_price  = $salePrice > 0 ? $salePrice : null;
            $product->image       = $image !== '' ? $image : null;
        } else {
            // Update meta only if allowed
            if ($this->updateMeta) {
                if ($name !== '')  $product->name  = $name;
                if ($price > 0)    $product->price = $price;

                // 0 or blank = clear discount; >0 = set discount
                $product->sale_price = $salePrice > 0 ? $salePrice : null;

                if ($image !== '') $product->image = $image;
            }
        }

        $product->save();
    }
}
