<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    // If you prefer, you can use $guarded = []; instead of $fillable.
    protected $fillable = [
        'customer_id',
        'status',
        'notes',

        'subtotal',
        'shipping_amount',
        'kdv_percent',   // <-- add
        'kdv_amount',    // <-- add
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
        'subtotal'        => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'kdv_percent'     => 'decimal:2',
        'kdv_amount'      => 'decimal:2',
        'total'           => 'decimal:2',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /* ----------------- Relationships ----------------- */

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

        // assumes public disk or default storage symlink
        return \Storage::url($this->pdf_path);
    }
}
