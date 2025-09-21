<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'status',
        'notes',

        'branch_id',
        'subtotal',
        'shipping_amount',
        'kdv_percent',
        'kdv_amount',
        'discount_percent',
        'discount_amount',
        'total',

        'billing_name',
        'billing_phone',
        'billing_address_line1',
        'billing_address_line2',
        'billing_city',
        'billing_state',
        'billing_postcode',
        'billing_country',

        'created_by_id',
        'pdf_path',
    ];

    protected $casts = [
        'subtotal'         => 'decimal:2',
        'shipping_amount'  => 'decimal:2',
        'kdv_percent'      => 'decimal:2',
        'kdv_amount'       => 'decimal:2',
        'discount_amount'   => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'total'            => 'decimal:2',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    /* ----------------- Relationships ----------------- */
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (blank($order->created_by_id)) {
                $order->created_by_id = auth()->id();
            }
        });
    }
    
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /* ----------------- Helpers ----------------- */

    public function getPdfUrlAttribute(): ?string
    {
        if (! $this->pdf_path) return null;

        return \Storage::url($this->pdf_path);
    }
    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }
}
