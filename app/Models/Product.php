<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Product.php
class Product extends Model
{
    protected $fillable = [
        'sku', 'wc_id', 'name', 'price', 'sale_price', 'image',
        // 'stock' is intentionally omitted to prevent mass-assignment via sync
        // add other fields you allow
    ];
}
