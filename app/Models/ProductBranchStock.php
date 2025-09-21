<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBranchStock extends Model
{
    protected $table = 'product_branch_stock';

    protected $fillable = [
        'product_id',
        'branch_id',
        'stock',
    ];
}
