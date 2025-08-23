<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['customer_id','status','notes','total'];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function items() { return $this->hasMany(OrderItem::class); }

    protected static function booted()
    {
        static::saving(function (Order $o) {
            $o->total = $o->items->sum(fn ($i) => $i->qty * $i->unit_price);
        });
    }
}
