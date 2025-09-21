<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Product.php
class Product extends Model
{
    protected $fillable = [
        'sku', 'wc_id', 'name', 'price', 'sale_price', 'image','stock'
        // 'stock' is intentionally omitted to prevent mass-assignment via sync
        // add other fields you allow
    ];

    public function stockColumn(): string
    {
        foreach (['stock', 'stock_quantity', 'quantity'] as $c) {
            // prefer actual attribute; if not set, check column existence
            if (array_key_exists($c, $this->getAttributes()) || Schema::hasColumn($this->getTable(), $c)) {
                return $c;
            }
        }
        // Fallback to "stock"
        return 'stock';
    }

    public function currentStock(): int
    {
        $col = $this->stockColumn();
        return (int) ($this->{$col} ?? 0);
    }

    public function decrementStock(int $by): void
    {
        $col = $this->stockColumn();
        $new = max($this->currentStock() - max($by, 0), 0);
        $this->forceFill([$col => $new])->saveQuietly();
    }

    public function incrementStock(int $by): void
    {
        $col = $this->stockColumn();
        $this->forceFill([$col => $this->currentStock() + max($by, 0)])->saveQuietly();
    }

    public function branchStocks()
    {
        return $this->hasMany(\App\Models\ProductBranchStock::class);
    }

}
