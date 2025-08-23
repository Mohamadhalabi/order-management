<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['wc_id','sku','name','price','stock','updated_from_wc_at'];

    protected $casts = [
        'price' => 'decimal:2',
        'updated_from_wc_at' => 'datetime',
    ];
}
