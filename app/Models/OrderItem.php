<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id','product_id','product_name','sku','stock_snapshot','image_url','qty','unit_price'];

    public function order()   { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
    
    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            // Normalize qty & price so DB never gets NULL/empty
            $item->qty        = (int) max(1, (int) ($item->qty ?? 1));
            $item->unit_price = (float) (($item->unit_price === '' || $item->unit_price === null) ? 0 : $item->unit_price);
            $item->line_total = round(((int)$item->qty) * ((float)$item->unit_price), 2);
        });
    }
}
